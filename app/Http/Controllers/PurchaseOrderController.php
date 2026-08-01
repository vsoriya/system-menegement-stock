<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\PurchaseOrderRequest;
use App\Http\Requests\ReceivePurchaseOrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = PurchaseOrder::query()
            ->with('supplier')
            ->withCount('lines')
            ->withSum('lines as ordered_units', 'quantity_ordered')
            ->withSum('lines as received_units', 'quantity_received')
            // Order value has to be summed as quantity * cost, which withSum
            // cannot express, so it comes from a correlated subquery.
            ->addSelect(['order_value' => PurchaseOrderLine::query()
                ->selectRaw('COALESCE(SUM(quantity_ordered * unit_cost), 0)')
                ->whereColumn('purchase_order_id', 'purchase_orders.id'),
            ])
            ->search($request->string('search')->toString())
            ->withStatus($request->string('status')->toString())
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('purchase-orders.index', [
            'orders' => $orders,
            'statuses' => PurchaseOrderStatus::options(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeManage();

        $order = new PurchaseOrder([
            'supplier_id' => $request->integer('supplier') ?: null,
            'ordered_at' => today(),
        ]);

        return view('purchase-orders.create', [
            'order' => $order,
            'nextNumber' => PurchaseOrder::nextNumber(),
            'suppliers' => $this->supplierOptions(),
            'products' => $this->productCatalogue(),
            'lineRows' => $this->lineRows(null),
        ]);
    }

    public function store(PurchaseOrderRequest $request, PurchaseOrderService $service): RedirectResponse
    {
        $order = $service->create($request->payload(), $request->lines(), $request->user());

        return redirect()
            ->route('purchase-orders.show', $order)
            ->with('status', __('app.po.created_msg', ['number' => $order->number]));
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['supplier', 'creator', 'approver', 'lines.product']);

        return view('purchase-orders.show', [
            'order' => $purchaseOrder,
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        $this->authorizeManage();

        if (! $purchaseOrder->status->isEditable()) {
            return $this->refuse($purchaseOrder, __('app.po.only_draft_editable'));
        }

        $purchaseOrder->load('lines.product');

        return view('purchase-orders.edit', [
            'order' => $purchaseOrder,
            'suppliers' => $this->supplierOptions(),
            'products' => $this->productCatalogue($purchaseOrder),
            'lineRows' => $this->lineRows($purchaseOrder),
        ]);
    }

    public function update(
        PurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        PurchaseOrderService $service,
    ): RedirectResponse {
        // Re-checked here as well, so a stale form cannot rewrite a signed off
        // order that changed status in another tab.
        if (! $purchaseOrder->status->isEditable()) {
            return $this->refuse($purchaseOrder, __('app.po.only_draft_editable'));
        }

        $service->update($purchaseOrder, $request->payload(), $request->lines());

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('status', __('app.po.updated_msg', ['number' => $purchaseOrder->number]));
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canDelete(), 403);

        $purchaseOrder->load('lines');

        // Deleting would orphan the stock movements the receipt created.
        if ($purchaseOrder->has_receipts) {
            return $this->refuse($purchaseOrder, __('app.po.cannot_delete'));
        }

        $number = $purchaseOrder->number;

        // Lines are removed by the cascade on the foreign key.
        $purchaseOrder->delete();

        return redirect()
            ->route('purchase-orders.index')
            ->with('status', __('app.po.deleted_msg', ['number' => $number]));
    }

    public function approve(
        Request $request,
        PurchaseOrder $purchaseOrder,
        PurchaseOrderService $service,
    ): RedirectResponse {
        $this->authorizeManage();

        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
            return $this->refuse($purchaseOrder, __('app.po.only_draft_editable'));
        }

        if ($purchaseOrder->lines()->doesntExist()) {
            return $this->refuse($purchaseOrder, __('app.po.no_lines_sub'));
        }

        $service->approve($purchaseOrder, $request->user());

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('status', __('app.po.approved_msg', ['number' => $purchaseOrder->number]));
    }

    public function cancel(PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $this->authorizeManage();

        if (! $purchaseOrder->status->isCancellable()) {
            return $this->refuse($purchaseOrder, __('app.po.cannot_cancel'));
        }

        $service->cancel($purchaseOrder);

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('status', __('app.po.cancelled_msg', ['number' => $purchaseOrder->number]));
    }

    /**
     * The delivery sheet, listing only what is still outstanding.
     */
    public function receive(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        $this->authorizeManage();

        if (! $purchaseOrder->status->isReceivable()) {
            return $this->refuse($purchaseOrder, __('app.po.only_approved_receivable'));
        }

        $purchaseOrder->load('lines.product');

        $outstanding = $purchaseOrder->lines
            ->filter(fn (PurchaseOrderLine $line): bool => $line->outstanding > 0)
            ->values();

        if ($outstanding->isEmpty()) {
            return $this->refuse($purchaseOrder, __('app.po.nothing_to_receive'));
        }

        return view('purchase-orders.receive', [
            'order' => $purchaseOrder,
            'lines' => $outstanding,
        ]);
    }

    public function storeReceipt(
        ReceivePurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        PurchaseOrderService $service,
    ): RedirectResponse {
        if (! $purchaseOrder->status->isReceivable()) {
            return $this->refuse($purchaseOrder, __('app.po.only_approved_receivable'));
        }

        $received = $service->receive(
            $purchaseOrder,
            $request->quantities(),
            $request->string('note')->toString() ?: null,
            $request->user(),
        );

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('status', __('app.po.received_msg', [
                'number' => $purchaseOrder->number,
                'count' => $received,
            ]));
    }

    /**
     * @return array<int, string>
     */
    protected function supplierOptions(): array
    {
        return Supplier::query()
            ->active()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Products that may be put on an order.
     *
     * Products already on the order are always included, so an item that was
     * deactivated after the order was raised does not vanish from the form.
     *
     * @return Collection<int, Product>
     */
    protected function productCatalogue(?PurchaseOrder $order = null): Collection
    {
        $onOrder = $order?->lines->pluck('product_id')->filter()->all() ?? [];

        return Product::query()
            ->where(function (Builder $query) use ($onOrder): void {
                $query->active();

                if ($onOrder !== []) {
                    $query->orWhereIn('id', $onOrder);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit', 'cost_price']);
    }

    /**
     * Rows used to seed the line editor: the rejected input if the form is
     * coming back from a validation failure, otherwise the saved lines.
     *
     * @return list<array<string, string>>
     */
    protected function lineRows(?PurchaseOrder $order): array
    {
        $submitted = old('lines');

        if (is_array($submitted)) {
            return collect($submitted)
                ->map(fn ($line): array => [
                    'product_id' => (string) (is_array($line) ? ($line['product_id'] ?? '') : ''),
                    'quantity_ordered' => (string) (is_array($line) ? ($line['quantity_ordered'] ?? '') : ''),
                    'unit_cost' => (string) (is_array($line) ? ($line['unit_cost'] ?? '') : ''),
                ])
                ->values()
                ->all();
        }

        if ($order !== null && $order->lines->isNotEmpty()) {
            return $order->lines
                ->map(fn (PurchaseOrderLine $line): array => [
                    'product_id' => (string) $line->product_id,
                    'quantity_ordered' => (string) $line->quantity_ordered,
                    'unit_cost' => (string) $line->unit_cost,
                ])
                ->values()
                ->all();
        }

        return [['product_id' => '', 'quantity_ordered' => '1', 'unit_cost' => '']];
    }

    /**
     * Send the user back to the order with an explanation.
     */
    protected function refuse(PurchaseOrder $order, string $message): RedirectResponse
    {
        return redirect()
            ->route('purchase-orders.show', $order)
            ->with('error', $message);
    }

    protected function authorizeManage(): void
    {
        abort_unless((bool) request()->user()?->canManageCatalog(), 403);
    }
}
