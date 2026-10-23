<?php

use App\Models\Site;
use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('components.layouts.app', ['title' => ['a', 'b']])]
class extends Component {
    use AtomComponent;

    public function with(): array
    {
        return ['rows' => Site::query()->paginate(10)];
    }
};
?>

<div>
    <atom:table :rows="$rows"></atom:table>
</div>
