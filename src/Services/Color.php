<?php
namespace Jiannius\Atom\Services;

use Illuminate\Support\Arr;

class Color
{
    // public $input;
    // public $options;
    // public $variation;

    // // contructor
    // public function __construct($input = null)
    // {
    //     $this->input = $input ? str($input)->lower()->toString() : null;
    //     $this->options = collect(json_decode(file_get_contents(atom_path('resources/json/colors.json')), true));
    // }

    // // to string
    // public function __toString() : string
    // {
    //     return $this->value() ?? '';
    // }

    // // set variation to inverted
    // public function inverted() : mixed
    // {
    //     $this->variation = 'inverted';
        
    //     return $this;
    // }

    // // set variation to light
    // public function light() : mixed
    // {
    //     $this->variation = 'light';

    //     return $this;
    // }

    // // set variation to dark
    // public function dark() : mixed
    // {
    //     $this->variation = 'dark';

    //     return $this;
    // }

    // // check input is hex
    // public function isHex() : bool
    // {
    //     $validator = validator(['color' => $this->input], ['color' => 'hex_color']);

    //     return $validator->passes();
    // }

    // // get value
    // public function value() : mixed
    // {
    //     if (!$this->isHex()) $this->convertInputToHex();
    //     if ($this->isHex() && $this->variation) return $this->getVariationValue();

    //     return $this->input;
    // }

    // // get value from position
    // public function pos($pos) : mixed
    // {
    //     return data_get($this->options, $this->input.'.'.$pos);
    // }

    /**
     * Get all colors
     */
    public static function all() : array
    {
        return json_decode(file_get_contents(__DIR__.'/../../json/colors.json'), true);
    }

    /**
     * Get minimal colors
     */
    public static function minimal() : array
    {
        return collect(self::all())->map(fn ($key) => data_get($key, 5))->filter()->toArray();
    }

    /**
     * Shade a color
     */
    public static function shade($color, $percent, $alpha = 1)
    {
        if (!$color) return;

        $color = str_replace('#', '', $color);
        $rgb = '';

        if (strlen($color) == 3) {
            $r = hexdec(substr($color, 0, 1) . substr($color, 0, 1));
            $g = hexdec(substr($color, 1, 1) . substr($color, 1, 1));
            $b = hexdec(substr($color, 2, 1) . substr($color, 2, 1));
        } else {
            $r = hexdec(substr($color, 0, 2));
            $g = hexdec(substr($color, 2, 2));
            $b = hexdec(substr($color, 4, 2));
        }

        $rgb = [$r, $g, $b];
        $rgb = Arr::map($rgb, fn ($x) => dechex($x));
        $rgb = Arr::map($rgb, fn ($x) => round(hexdec($x) + round(((255 - hexdec($x)) * $percent) / 100)));

        if ($alpha < 1) $rgb = 'rgba(' . implode(',', $rgb) . ', ' . $alpha . ')';
        else $rgb = '#'.implode('',Arr::map($rgb, fn ($x) => str_pad(dechex($x), 2, '0', STR_PAD_LEFT)));

        return $rgb;
    }

    // // convert input to hex
    // public function convertInputToHex() : void
    // {
    //     if ($this->input === 'black') $this->input = '#000000';
    //     if ($this->input === 'white') $this->input = '#ffffff';
    //     if (in_array($this->input, ['gray', 'zinc', 'neutral'])) $this->input = 'slate';

    //     if ($hex = data_get($this->options, $this->input.'.4')) $this->input = $hex;
    // }

    // // get variation value
    // public function getVariationValue() : mixed
    // {
    //     if ($group = $this->options->where(fn($val) => in_array($this->input, $val))->first()) {
    //         $len = count($group);
    //         $pos = collect($group)->search($this->input);

    //         if ($this->variation === 'inverted') {
    //             if ($this->input === '#000000') return '#ffffff';
    //             elseif ($this->input === '#ffffff') return '#000000';
    //             else {
    //                 if ($pos <= 3) return '#ffffff';

    //                 $pos = $pos - 4;

    //                 if ($pos < 0) $pos = 0;

    //                 return $group[$pos];
    //             }
    //         }
    //         elseif ($this->variation === 'light') {
    //             if ($pos <= 3) return $this->input;
    
    //             return $group[$pos - 2];
    //         }
    //         elseif ($this->variation === 'dark') {
    //             $pos = $pos + 4;
                
    //             if ($pos > $len - 1) $pos = $len - 1;
                
    //             return $group[$pos];
    //         }
    //     }

    //     return null;
    // }
}