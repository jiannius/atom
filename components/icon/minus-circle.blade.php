@props([
'variant' => null,
])

<atom:icon._wrapper :attributes="$attributes">
   @if ($variant === 'solid')
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
         <path fill-rule="evenodd"
            d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm3 10.5a.75.75 0 0 0 0-1.5H9a.75.75 0 0 0 0 1.5h6Z"
            clip-rule="evenodd" />
      </svg>
   @else
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
         class="lucide lucide-circle-minus-icon lucide-circle-minus">
         <circle cx="12" cy="12" r="10" />
         <path d="M8 12h8" />
      </svg>
   @endif
</atom:icon._wrapper>