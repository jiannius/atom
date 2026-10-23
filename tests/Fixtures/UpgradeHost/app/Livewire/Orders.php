<?php

namespace App\Livewire;

use Jiannius\Atom\Services\Carbon;
use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

class Orders extends Component
{
    use AtomComponent;

    protected $listeners = ['saved' => 'toast'];

    public function breadcrumbs($breadcrumbs)
    {
        $breadcrumbs->push('Orders');
    }

    public function render()
    {
        return view('livewire.orders', [
            'orders' => Order::query()->paginate(20),
        ]);
    }

    public function mountAtomComponent()
    {
    }

    public function since(Carbon $date, ?Carbon $until): bool
    {
        return $date instanceof Carbon;
    }

    public function dates($order, $request)
    {
        $a = $order->placed_at->pretty();
        $b = $order->placed_at?->local();
        $c = $order->placed_at->recent();
        $d = $order->range->toDateRangeString();
        // $order->placed_at->pretty() is commented out
        $e = $this->local();
        $f = app('livewire')->isLivewireRequest();
        $g = $request->isLivewireRequest();
        $h = $request->subdomain();
        $i = str('a.b')->dotpath();
        $j = Order::query()->tableHasColumn('x');
    }
}
