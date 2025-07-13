<?php

namespace Jiannius\Atom\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;

class GetOptions
{
    public $name;
    public $filters;
    public $selected = [];
    public $options = [];
    public $exclude = [];

    /**
     * Handle the action
     */
    public function handle($params)
    {
        $this->name = data_get($params, 'name');
        $this->filters = data_get($params, 'filters');
        $this->selected = $this->filters ? (array)Arr::pull($this->filters, 'value') : [];
        $this->exclude = $this->filters ? (array)Arr::pull($this->filters, 'exclude') : [];

        $options = method_exists($this, $this->name)
            ? $this->{$this->name}()
            : $this->getFromJson();

        return Arr::map($options, fn ($option) => $this->getOptionHtml($option));
    }

    /**
     * Get countries
     */
    public function countries() : array
    {
        return collect($this->getFromJson('countries'))
            ->map(fn ($item) => [
                'value' => data_get($item, 'iso_code'),
                'label' => data_get($item, 'name'),
            ])
            ->sortBy('label')
            ->values()
            ->toArray();
    }

    /**
     * Get states
     */
    public function states() : array
    {
        $countries = collect($this->getFromJson('countries'));
        $country = $countries->firstWhere('iso_code', data_get($this->filters, 'country') ?? 'MY');
        $states = data_get($country, 'states');

        return collect($states)
            ->map(fn ($item) => [
                'value' => data_get($item, 'name'),
                'label' => data_get($item, 'name'),
            ])
            ->sortBy('label')
            ->values()
            ->toArray();
    }

    /**
     * Get dial codes
     */
    public function dialcodes() : array
    {
        return collect($this->getFromJson('countries'))
            ->map(fn ($item) => [
                'value' => data_get($item, 'dial_code'),
                'label' => data_get($item, 'iso_code').' ('.data_get($item, 'dial_code').')',
            ])
            ->sortBy('label')
            ->values()
            ->toArray();
    }

    /**
     * Get currencies
     */
    public function currencies() : array
    {
        $countries = collect($this->getFromJson('countries'));

        return $countries
            ->map(fn ($item) => [
                'value' => data_get($item, 'currency.code'),
                'label' => collect([data_get($item, 'currency.code'), data_get($item, 'name')])->filter()->join(' - '),
            ])
            ->filter(fn ($item) => !empty(data_get($item, 'value')))
            ->values()
            ->sortBy('label')
            ->values()
            ->toArray();
    }

    /**
     * Get options from json file
     */
    public function getFromJson($name = null)
    {
        $name ??= $this->name;
        $cached = cache('_options') ?? [];
        $options = data_get($cached, $name);

        if ($options) return $options;

        $path = resource_path('json/'.$name.'.json');
        $local = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
        $atom = json_decode(file_get_contents(__DIR__.'/../../json/'.$name.'.json'), true);
        $options = array_merge_recursive($atom, $local);
        $cached[$name] = $options;

        cache(['_options' => $cached]);

        return $options;
    }

    /**
     * Get option html
     */
    public function getOptionHtml($option)
    {
        if (data_get($option, 'html')) return $option;

        $label = '<div class="text-wrap">'.data_get($option, 'label').'</div>';
        $caption = data_get($option, 'caption') ? '<div class="text-muted text-sm text-wrap">'.data_get($option, 'caption').'</div>' : '';
        $avatar = data_get($option, 'avatar')
            ? Blade::render('<atom:avatar size="xs" :avatar="$avatar">{{ $name }}</atom:avatar>', ['name' => data_get($option, 'label'), 'avatar' => data_get($option, 'avatar')])
            : '';

        return [
            ...$option,
            'html' => $avatar
                ? <<<EOL
                <div class="w-full flex items-center gap-2">
                    <div class="shrink-0">
                    {$avatar}
                    </div>
                    <div class="grow">
                    {$label}
                    {$caption}
                    </div>
                </div>
                EOL
                : <<<EOL
                <div class="w-full">
                    {$label}
                    {$caption}
                </div>
                EOL,
        ];
    }
}
