{{-- The mark for a value that is not there: a muted dash that a screen reader skips, and a "Not set" it reads instead. Private
     (the underscore keeps it out of the docs): <atom:dd>, <atom:table.cell> and <atom:page.header> draw it, so a host never
     types a "—" or "--" of its own. --}}
<span {{ $attributes->class('text-fg-subtle') }} data-atom-missing><span aria-hidden="true">—</span><span class="sr-only">{{ t('Not set') }}</span></span>
