{{-- The totals strip: stats side by side under one hairline, wrapping to two a row on a phone. The dividers are the
     1px gap showing the group's own background, so a wrap never leaves a stray border. Sizing and chrome of the stats
     inside it are in atom.css (the rig has no Tailwind build). --}}
<div {{ $attributes->class('flex flex-wrap gap-px overflow-hidden rounded-panel border bg-border bg-clip-padding') }} data-atom-stat-group>
    {{ $slot }}
</div>
