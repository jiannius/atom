<div class="space-y-8" data-testid="tiles-root">
    <section class="space-y-3" data-testid="radio-section">
        <atom:radio.group label="Plan" caption="Switch any time." variant="tile" data-testid="plan-group">
            <atom:radio wire:model.live="plan" name="plan" value="starter" label="Starter" caption="RM 50 a month"/>
            <atom:radio wire:model.live="plan" name="plan" value="growth" label="Growth" caption="RM 120 a month"/>
            <atom:radio wire:model.live="plan" name="plan" value="scale" disabled label="Scale" caption="Sold out"/>
            <atom:radio wire:model.live="plan" name="plan" value="custom">
                <div class="font-medium">Custom</div>
                <div class="text-2xl font-semibold" data-testid="price">RM 999</div>
            </atom:radio>
        </atom:radio.group>

        <div>Plan: <span data-testid="server-plan">{{ $plan }}</span></div>
    </section>

    <section class="space-y-3" data-testid="checkbox-section">
        <atom:checkbox.group variant="tile" role="group" aria-label="Extras" data-testid="extras-group">
            <atom:checkbox wire:model.live="extras" value="gift" label="Gift wrap" caption="RM 5"/>
            <atom:checkbox wire:model.live="extras" value="express" label="Express delivery" caption="Next day"/>
            <atom:checkbox wire:model.live="extras" value="insurance" label="Insurance" caption="Not for this order" disabled/>
        </atom:checkbox.group>

        <div>Extras: <span data-testid="server-extras">{{ implode(',', $extras) }}</span></div>
    </section>

    <section class="space-y-3" data-testid="alpine-section" x-data="{ size: 'm', toppings: ['cheese'] }">
        <atom:radio.group label="Size" variant="tile" data-testid="size-group">
            <atom:radio x-model="size" name="size" value="s" label="Small"/>
            <atom:radio x-model="size" name="size" value="m" label="Medium"/>
            <atom:radio x-model="size" name="size" value="l" label="Large"/>
        </atom:radio.group>

        <atom:checkbox.group variant="tile" role="group" aria-label="Toppings" data-testid="toppings-group">
            <atom:checkbox x-model="toppings" name="toppings[]" value="cheese" label="Cheese"/>
            <atom:checkbox x-model="toppings" name="toppings[]" value="olives" label="Olives"/>
        </atom:checkbox.group>

        <div>Size: <span data-testid="alpine-size" x-text="size"></span> Toppings: <span data-testid="alpine-toppings" x-text="toppings.join(',')"></span></div>
    </section>

    <section class="space-y-3" data-testid="invalid-section">
        <atom:radio.group label="Delivery" variant="tile" error="Choose a delivery option" data-testid="invalid-radios">
            <atom:radio name="delivery" value="post" label="Post"/>
            <atom:radio name="delivery" value="pickup" label="Pickup"/>
        </atom:radio.group>

        <atom:checkbox.group variant="tile" role="group" aria-label="Terms" data-testid="invalid-checkboxes">
            <atom:checkbox name="terms" value="1" label="Terms" caption="I accept" error="Accept the terms"/>
        </atom:checkbox.group>
    </section>

    <section class="space-y-3" data-testid="scoped-section">
        {{-- a tile group beside a control with an error of its own, in one field: only the error under the group reddens the tiles --}}
        <atom:input.field label="Mixed">
            <atom:radio.group variant="tile" data-testid="mixed-radios">
                <atom:radio name="mixed" value="a" label="Alpha"/>
                <atom:radio name="mixed" value="b" label="Beta"/>
            </atom:radio.group>
            <atom:input name="note" label="Note" error="Note is bad"/>
        </atom:input.field>
    </section>

    <section class="space-y-3" data-testid="disabled-section">
        <atom:radio.group label="Locked" variant="tile" disabled data-testid="disabled-radios">
            <atom:radio name="locked" value="a" label="One" checked/>
            <atom:radio name="locked" value="b" label="Two"/>
        </atom:radio.group>

        <atom:form disabled data-testid="disabled-form">
            <atom:checkbox.group variant="tile" role="group" aria-label="Frozen">
                <atom:checkbox name="frozen" value="a" label="Frozen" checked/>
            </atom:checkbox.group>
        </atom:form>
    </section>

    <section class="space-y-3" data-testid="card-section">
        <atom:radio.group label="Billing" variant="card" data-testid="card-group">
            <atom:radio name="billing" value="monthly" label="Monthly" caption="RM 50 a month" align="start"/>
            <atom:radio name="billing" value="yearly" label="Yearly" caption="RM 500 a year" align="start"/>
        </atom:radio.group>
    </section>

    <div>
        <atom:button type="button" wire:click="bump" data-testid="bump">Refresh</atom:button>
        <span data-testid="ticks">{{ $ticks }}</span>
    </div>
</div>
