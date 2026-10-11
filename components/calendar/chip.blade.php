{{-- One item as the calendar draws it, for Alpine's x-for: `chip` is the scope variable, an item plus
     where it sits. A bar (all-day, or crossing a date) is a tinted block with an accent edge; a timed
     item within one day is a dot, its time and its title on the ground. Colour is decoration and the
     text stays neutral, so any colour reads. The classes of a colour come from the palette in
     calendar/index.blade.php through colorClass(); a danger item swaps in red and an icon, so it is
     not colour alone. Each is named whole by itemLabel(), and a tooltip with the title alone is put on only while the title shown
     is cut short (tooltip()), so a screen reader does not read it twice. A press on one starts a move (itemPress()), and on its handle, the
     end of a bar or the bottom edge of a block, a resize: the handle is for the pointer alone and is not in the tab order or the accessibility tree,
     as an item is moved and resized with the pointer only; what is done with the keyboard is the item's own click. --}}
<template x-if="chip.kind === 'bar'">
    <a
    x-bind:href="link(chip)"
    x-bind:role="link(chip) ? null : 'button'"
    x-bind:tabindex="link(chip) ? null : 0"
    x-bind:aria-label="itemLabel(chip)"
    x-bind:data-before="chip.before ? '' : null"
    x-bind:data-after="chip.after ? '' : null"
    x-bind:data-lifted="isLifted(chip) ? '' : null"
    x-bind:data-atom-calendar-hex="isHex(chip) && !chip.danger ? '' : null"
    x-bind:style="hue(chip)"
    x-bind:class="[colorClass(chip, 'tint'), chip.danger ? 'border-red-600 dark:border-red-400' : colorClass(chip, 'accent')]"
    x-on:pointerdown="itemPress(chip, $event)"
    x-bind:data-draggable="canDrag(chip) ? '' : null"
    x-bind:draggable="canDrag(chip) ? 'false' : null"
    x-on:click="itemClick(chip, $event)"
    x-on:keydown.enter="itemKey(chip, $event)"
    x-on:keydown.space="itemKey(chip, $event)"
    x-on:mouseenter="tooltip($el)"
    data-atom-calendar-chip="bar"
    class="flex h-5 w-full min-w-0 cursor-pointer items-center gap-1 rounded-sm border-l-[3px] px-1.5 text-xs font-medium outline-offset-1 hover:brightness-95 dark:hover:brightness-125 focus-visible:outline-2 focus-visible:outline-zinc-900 dark:focus-visible:outline-white">
        <span x-show="chip.danger || chip.icon" x-effect="setIcon($el, chip.danger ? 'warning' : chip.icon)" x-bind:class="chip.danger ? 'text-red-700 dark:text-red-300' : 'text-zinc-600 dark:text-zinc-300'" class="inline-flex shrink-0"></span>
        <span x-text="chip.title" x-bind:class="chip.danger ? 'text-red-700 dark:text-red-300' : 'text-zinc-800 dark:text-zinc-100'" data-atom-calendar-chip-title class="truncate"></span>
        <span x-show="chip.danger" x-text="labels.ui?.overdue" data-atom-calendar-sr></span>
        <span x-show="canResize(chip)" aria-hidden="true" data-atom-calendar-handle="end"></span>
    </a>
</template>

<template x-if="chip.kind === 'dot'">
    <a
    x-bind:href="link(chip)"
    x-bind:role="link(chip) ? null : 'button'"
    x-bind:tabindex="link(chip) ? null : 0"
    x-bind:aria-label="itemLabel(chip)"
    x-bind:data-lifted="isLifted(chip) ? '' : null"
    x-on:pointerdown="itemPress(chip, $event)"
    x-bind:data-draggable="canDrag(chip) ? '' : null"
    x-bind:draggable="canDrag(chip) ? 'false' : null"
    x-on:click="itemClick(chip, $event)"
    x-on:keydown.enter="itemKey(chip, $event)"
    x-on:keydown.space="itemKey(chip, $event)"
    x-on:mouseenter="tooltip($el)"
    data-atom-calendar-chip="dot"
    class="flex h-5 w-full min-w-0 cursor-pointer items-center gap-1.5 rounded-sm px-1 text-xs outline-offset-1 hover:bg-zinc-100 dark:hover:bg-zinc-700/60 focus-visible:outline-2 focus-visible:outline-zinc-900 dark:focus-visible:outline-white">
        <span
        x-bind:class="chip.danger ? 'bg-red-600 dark:bg-red-400' : colorClass(chip, 'dot')"
        x-bind:data-atom-calendar-hex="isHex(chip) && !chip.danger ? '' : null"
        x-bind:style="hue(chip)"
        data-atom-calendar-dot
        class="size-2 shrink-0 rounded-full"></span>
        <span x-text="timeLabel(chip)" class="shrink-0 font-normal tabular-nums text-zinc-600 dark:text-zinc-400"></span>
        <span x-show="chip.danger || chip.icon" x-effect="setIcon($el, chip.danger ? 'warning' : chip.icon)" x-bind:class="chip.danger ? 'text-red-700 dark:text-red-300' : 'text-zinc-600 dark:text-zinc-300'" class="inline-flex shrink-0"></span>
        <span x-text="chip.title" x-bind:class="chip.danger ? 'text-red-700 dark:text-red-300' : 'text-zinc-800 dark:text-zinc-100'" data-atom-calendar-chip-title class="truncate font-medium"></span>
        <span x-show="chip.danger" x-text="labels.ui?.overdue" data-atom-calendar-sr></span>
    </a>
</template>

{{-- A timed item in a day column of the week and day views: a block as tall as it lasts, in its lane. Short ones print
     "Title · 10:00" on one line, longer ones the time range under the title. Its name is said whole (itemLabel()): the date,
     the time range and what its colour or overdue stands for are not in its text alone, which a narrow block drops in part. --}}
<template x-if="chip.kind === 'block'">
    <a
    x-bind:href="link(chip)"
    x-bind:role="link(chip) ? null : 'button'"
    x-bind:tabindex="link(chip) ? null : 0"
    x-bind:data-before="chip.before ? '' : null"
    x-bind:data-after="chip.after ? '' : null"
    x-bind:aria-label="itemLabel(chip)"
    x-bind:data-compact="chip.compact ? '' : null"
    x-bind:data-lifted="isLifted(chip) ? '' : null"
    x-bind:data-atom-calendar-hex="isHex(chip) && !chip.danger ? '' : null"
    x-bind:style="hue(chip)"
    x-bind:class="[colorClass(chip, 'tint'), chip.danger ? 'border-red-600 dark:border-red-400' : colorClass(chip, 'accent')]"
    x-on:pointerdown="itemPress(chip, $event)"
    x-bind:data-draggable="canDrag(chip) ? '' : null"
    x-bind:draggable="canDrag(chip) ? 'false' : null"
    x-on:click="itemClick(chip, $event)"
    x-on:keydown.enter="itemKey(chip, $event)"
    x-on:keydown.space="itemKey(chip, $event)"
    data-atom-calendar-chip="block"
    class="flex h-full w-full min-w-0 cursor-pointer flex-col overflow-hidden rounded-md border-l-[3px] px-1.5 py-0.5 text-xs ring-1 ring-white dark:ring-zinc-800 outline-offset-1 hover:brightness-95 dark:hover:brightness-125 focus-visible:outline-2 focus-visible:outline-zinc-900 dark:focus-visible:outline-white">
        <span class="flex min-w-0 items-center gap-1">
            <span x-show="chip.danger || chip.icon" x-effect="setIcon($el, chip.danger ? 'warning' : chip.icon)" x-bind:class="chip.danger ? 'text-red-700 dark:text-red-300' : 'text-zinc-600 dark:text-zinc-300'" class="inline-flex shrink-0"></span>
            <span x-text="chip.title" x-bind:class="chip.danger ? 'text-red-700 dark:text-red-300' : 'text-zinc-800 dark:text-zinc-100'" data-atom-calendar-item-title class="truncate font-semibold"></span>
            <span x-show="chip.compact" x-text="'· ' + timeLabel(chip)" data-atom-calendar-item-time class="shrink-0 font-normal tabular-nums text-zinc-600 dark:text-zinc-300"></span>
            <span x-show="chip.danger" x-text="labels.ui?.overdue" data-atom-calendar-sr></span>
        </span>
        <span x-show="!chip.compact" x-text="timeRange(chip)" data-atom-calendar-item-time class="truncate font-normal tabular-nums text-zinc-600 dark:text-zinc-300"></span>
        <span x-show="canResize(chip)" aria-hidden="true" data-atom-calendar-handle="end"></span>
    </a>
</template>
