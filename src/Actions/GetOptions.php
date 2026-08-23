<?php

namespace Jiannius\Atom\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Jiannius\Atom\Contracts\WebAction;

/**
 * Web-callable: <atom:select :callback> fetches its options from the browser,
 * and those selects appear on guest-facing forms (country, state, dial code),
 * so this stays reachable without authentication. It only ever reads option
 * lists — keep it that way.
 */
class GetOptions implements WebAction
{
    public $name;
    public $filters;
    public $selected = [];
    public $options = [];
    public $exclude = [];

    const DIAL_CODES = [
        ['iso' => 'AC', 'code' => '+247', 'name' => 'Ascension Island'],
        ['iso' => 'AD', 'code' => '+376', 'name' => 'Andorra'],
        ['iso' => 'AE', 'code' => '+971', 'name' => 'United Arab Emirates'],
        ['iso' => 'AF', 'code' => '+93', 'name' => 'Afghanistan'],
        ['iso' => 'AG', 'code' => '+1268', 'name' => 'Antigua and Barbuda'],
        ['iso' => 'AI', 'code' => '+1264', 'name' => 'Anguilla'],
        ['iso' => 'AL', 'code' => '+355', 'name' => 'Albania'],
        ['iso' => 'AM', 'code' => '+374', 'name' => 'Armenia'],
        ['iso' => 'AN', 'code' => '+599', 'name' => 'Netherlands Antilles'],
        ['iso' => 'AO', 'code' => '+244', 'name' => 'Angola'],
        ['iso' => 'AQ', 'code' => '+672', 'name' => 'Antarctica'],
        ['iso' => 'AR', 'code' => '+54', 'name' => 'Argentina'],
        ['iso' => 'AS', 'code' => '+1684', 'name' => 'American Samoa'],
        ['iso' => 'AT', 'code' => '+43', 'name' => 'Austria'],
        ['iso' => 'AU', 'code' => '+61', 'name' => 'Australia'],
        ['iso' => 'AW', 'code' => '+297', 'name' => 'Aruba'],
        ['iso' => 'AX', 'code' => '+358', 'name' => 'Åland Islands'],
        ['iso' => 'AZ', 'code' => '+994', 'name' => 'Azerbaijan'],
        ['iso' => 'BA', 'code' => '+387', 'name' => 'Bosnia and Herzegovina'],
        ['iso' => 'BB', 'code' => '+1246', 'name' => 'Barbados'],
        ['iso' => 'BD', 'code' => '+880', 'name' => 'Bangladesh'],
        ['iso' => 'BE', 'code' => '+32', 'name' => 'Belgium'],
        ['iso' => 'BF', 'code' => '+226', 'name' => 'Burkina Faso'],
        ['iso' => 'BG', 'code' => '+359', 'name' => 'Bulgaria'],
        ['iso' => 'BH', 'code' => '+973', 'name' => 'Bahrain'],
        ['iso' => 'BI', 'code' => '+257', 'name' => 'Burundi'],
        ['iso' => 'BJ', 'code' => '+229', 'name' => 'Benin'],
        ['iso' => 'BL', 'code' => '+590', 'name' => 'Saint Barthélemy'],
        ['iso' => 'BM', 'code' => '+1441', 'name' => 'Bermuda'],
        ['iso' => 'BN', 'code' => '+673', 'name' => 'Brunei'],
        ['iso' => 'BO', 'code' => '+591', 'name' => 'Bolivia'],
        ['iso' => 'BR', 'code' => '+55', 'name' => 'Brazil'],
        ['iso' => 'BS', 'code' => '+1242', 'name' => 'The Bahamas'],
        ['iso' => 'BT', 'code' => '+975', 'name' => 'Bhutan'],
        ['iso' => 'BW', 'code' => '+267', 'name' => 'Botswana'],
        ['iso' => 'BY', 'code' => '+375', 'name' => 'Belarus'],
        ['iso' => 'BZ', 'code' => '+501', 'name' => 'Belize'],
        ['iso' => 'CA', 'code' => '+1', 'name' => 'Canada'],
        ['iso' => 'CC', 'code' => '+61', 'name' => 'Cocos (Keeling) Islands'],
        ['iso' => 'CD', 'code' => '+243', 'name' => 'Democratic Republic of the Congo'],
        ['iso' => 'CF', 'code' => '+236', 'name' => 'Central African Republic'],
        ['iso' => 'CG', 'code' => '+242', 'name' => 'Republic of the Congo'],
        ['iso' => 'CH', 'code' => '+41', 'name' => 'Switzerland'],
        ['iso' => 'CI', 'code' => '+225', 'name' => 'Ivory Coast'],
        ['iso' => 'CK', 'code' => '+682', 'name' => 'Cook Islands'],
        ['iso' => 'CL', 'code' => '+56', 'name' => 'Chile'],
        ['iso' => 'CM', 'code' => '+237', 'name' => 'Cameroon'],
        ['iso' => 'CN', 'code' => '+86', 'name' => 'China'],
        ['iso' => 'CO', 'code' => '+57', 'name' => 'Colombia'],
        ['iso' => 'CR', 'code' => '+506', 'name' => 'Costa Rica'],
        ['iso' => 'CU', 'code' => '+53', 'name' => 'Cuba'],
        ['iso' => 'CV', 'code' => '+238', 'name' => 'Cape Verde'],
        ['iso' => 'CX', 'code' => '+61', 'name' => 'Christmas Island'],
        ['iso' => 'CY', 'code' => '+357', 'name' => 'Cyprus'],
        ['iso' => 'CZ', 'code' => '+420', 'name' => 'Czech Republic'],
        ['iso' => 'DE', 'code' => '+49', 'name' => 'Germany'],
        ['iso' => 'DJ', 'code' => '+253', 'name' => 'Djibouti'],
        ['iso' => 'DK', 'code' => '+45', 'name' => 'Denmark'],
        ['iso' => 'DM', 'code' => '+1767', 'name' => 'Dominica'],
        ['iso' => 'DO', 'code' => '+1849', 'name' => 'Dominican Republic'],
        ['iso' => 'DZ', 'code' => '+213', 'name' => 'Algeria'],
        ['iso' => 'EC', 'code' => '+593', 'name' => 'Ecuador'],
        ['iso' => 'EE', 'code' => '+372', 'name' => 'Estonia'],
        ['iso' => 'EG', 'code' => '+20', 'name' => 'Egypt'],
        ['iso' => 'ER', 'code' => '+291', 'name' => 'Eritrea'],
        ['iso' => 'ES', 'code' => '+34', 'name' => 'Spain'],
        ['iso' => 'ET', 'code' => '+251', 'name' => 'Ethiopia'],
        ['iso' => 'FI', 'code' => '+358', 'name' => 'Finland'],
        ['iso' => 'FJ', 'code' => '+679', 'name' => 'Fiji'],
        ['iso' => 'FK', 'code' => '+500', 'name' => 'Falkland Islands'],
        ['iso' => 'FM', 'code' => '+691', 'name' => 'Federated States of Micronesia'],
        ['iso' => 'FO', 'code' => '+298', 'name' => 'Faroe Islands'],
        ['iso' => 'FR', 'code' => '+33', 'name' => 'France'],
        ['iso' => 'GA', 'code' => '+241', 'name' => 'Gabon'],
        ['iso' => 'GB', 'code' => '+44', 'name' => 'United Kingdom'],
        ['iso' => 'GD', 'code' => '+1473', 'name' => 'Grenada'],
        ['iso' => 'GE', 'code' => '+995', 'name' => 'Georgia'],
        ['iso' => 'GF', 'code' => '+594', 'name' => 'French Guiana'],
        ['iso' => 'GG', 'code' => '+44', 'name' => 'Guernsey'],
        ['iso' => 'GH', 'code' => '+233', 'name' => 'Ghana'],
        ['iso' => 'GI', 'code' => '+350', 'name' => 'Gibraltar'],
        ['iso' => 'GL', 'code' => '+299', 'name' => 'Greenland'],
        ['iso' => 'GM', 'code' => '+220', 'name' => 'The Gambia'],
        ['iso' => 'GN', 'code' => '+224', 'name' => 'Guinea'],
        ['iso' => 'GP', 'code' => '+590', 'name' => 'Guadeloupe'],
        ['iso' => 'GQ', 'code' => '+240', 'name' => 'Equatorial Guinea'],
        ['iso' => 'GR', 'code' => '+30', 'name' => 'Greece'],
        ['iso' => 'GS', 'code' => '+500', 'name' => 'South Georgia'],
        ['iso' => 'GT', 'code' => '+502', 'name' => 'Guatemala'],
        ['iso' => 'GU', 'code' => '+1671', 'name' => 'Guam'],
        ['iso' => 'GW', 'code' => '+245', 'name' => 'Guinea-Bissau'],
        ['iso' => 'GY', 'code' => '+592', 'name' => 'Guyana'],
        ['iso' => 'HK', 'code' => '+852', 'name' => 'Hong Kong'],
        ['iso' => 'HN', 'code' => '+504', 'name' => 'Honduras'],
        ['iso' => 'HR', 'code' => '+385', 'name' => 'Croatia'],
        ['iso' => 'HT', 'code' => '+509', 'name' => 'Haiti'],
        ['iso' => 'HU', 'code' => '+36', 'name' => 'Hungary'],
        ['iso' => 'ID', 'code' => '+62', 'name' => 'Indonesia'],
        ['iso' => 'IE', 'code' => '+353', 'name' => 'Republic of Ireland'],
        ['iso' => 'IL', 'code' => '+972', 'name' => 'Israel'],
        ['iso' => 'IM', 'code' => '+44', 'name' => 'Isle of Man'],
        ['iso' => 'IN', 'code' => '+91', 'name' => 'India'],
        ['iso' => 'IO', 'code' => '+246', 'name' => 'British Indian Ocean Territory'],
        ['iso' => 'IQ', 'code' => '+964', 'name' => 'Iraq'],
        ['iso' => 'IR', 'code' => '+98', 'name' => 'Iran'],
        ['iso' => 'IS', 'code' => '+354', 'name' => 'Iceland'],
        ['iso' => 'IT', 'code' => '+39', 'name' => 'Italy'],
        ['iso' => 'JE', 'code' => '+44', 'name' => 'Jersey'],
        ['iso' => 'JM', 'code' => '+1876', 'name' => 'Jamaica'],
        ['iso' => 'JO', 'code' => '+962', 'name' => 'Jordan'],
        ['iso' => 'JP', 'code' => '+81', 'name' => 'Japan'],
        ['iso' => 'KE', 'code' => '+254', 'name' => 'Kenya'],
        ['iso' => 'KG', 'code' => '+996', 'name' => 'Kyrgyzstan'],
        ['iso' => 'KH', 'code' => '+855', 'name' => 'Cambodia'],
        ['iso' => 'KI', 'code' => '+686', 'name' => 'Kiribati'],
        ['iso' => 'KM', 'code' => '+269', 'name' => 'Comoros'],
        ['iso' => 'KN', 'code' => '+1869', 'name' => 'Saint Kitts and Nevis'],
        ['iso' => 'KP', 'code' => '+850', 'name' => 'North Korea'],
        ['iso' => 'KR', 'code' => '+82', 'name' => 'South Korea'],
        ['iso' => 'KW', 'code' => '+965', 'name' => 'Kuwait'],
        ['iso' => 'KY', 'code' => '+1345', 'name' => 'Cayman Islands'],
        ['iso' => 'KZ', 'code' => '+77', 'name' => 'Kazakhstan'],
        ['iso' => 'LA', 'code' => '+856', 'name' => 'Laos'],
        ['iso' => 'LB', 'code' => '+961', 'name' => 'Lebanon'],
        ['iso' => 'LC', 'code' => '+1758', 'name' => 'Saint Lucia'],
        ['iso' => 'LI', 'code' => '+423', 'name' => 'Liechtenstein'],
        ['iso' => 'LK', 'code' => '+94', 'name' => 'Sri Lanka'],
        ['iso' => 'LR', 'code' => '+231', 'name' => 'Liberia'],
        ['iso' => 'LS', 'code' => '+266', 'name' => 'Lesotho'],
        ['iso' => 'LT', 'code' => '+370', 'name' => 'Lithuania'],
        ['iso' => 'LU', 'code' => '+352', 'name' => 'Luxembourg'],
        ['iso' => 'LV', 'code' => '+371', 'name' => 'Latvia'],
        ['iso' => 'LY', 'code' => '+218', 'name' => 'Libya'],
        ['iso' => 'MA', 'code' => '+212', 'name' => 'Morocco'],
        ['iso' => 'MC', 'code' => '+377', 'name' => 'Monaco'],
        ['iso' => 'MD', 'code' => '+373', 'name' => 'Moldova'],
        ['iso' => 'ME', 'code' => '+382', 'name' => 'Montenegro'],
        ['iso' => 'MF', 'code' => '+590', 'name' => 'Saint Martin'],
        ['iso' => 'MG', 'code' => '+261', 'name' => 'Madagascar'],
        ['iso' => 'MH', 'code' => '+692', 'name' => 'Marshall Islands'],
        ['iso' => 'MK', 'code' => '+389', 'name' => 'Republic of Macedonia'],
        ['iso' => 'ML', 'code' => '+223', 'name' => 'Mali'],
        ['iso' => 'MM', 'code' => '+95', 'name' => 'Myanmar'],
        ['iso' => 'MN', 'code' => '+976', 'name' => 'Mongolia'],
        ['iso' => 'MO', 'code' => '+853', 'name' => 'Macau'],
        ['iso' => 'MP', 'code' => '+1670', 'name' => 'Northern Mariana Islands'],
        ['iso' => 'MQ', 'code' => '+596', 'name' => 'Martinique'],
        ['iso' => 'MR', 'code' => '+222', 'name' => 'Mauritania'],
        ['iso' => 'MS', 'code' => '+1664', 'name' => 'Montserrat'],
        ['iso' => 'MT', 'code' => '+356', 'name' => 'Malta'],
        ['iso' => 'MU', 'code' => '+230', 'name' => 'Mauritius'],
        ['iso' => 'MV', 'code' => '+960', 'name' => 'Maldives'],
        ['iso' => 'MW', 'code' => '+265', 'name' => 'Malawi'],
        ['iso' => 'MX', 'code' => '+52', 'name' => 'Mexico'],
        ['iso' => 'MY', 'code' => '+60', 'name' => 'Malaysia'],
        ['iso' => 'MZ', 'code' => '+258', 'name' => 'Mozambique'],
        ['iso' => 'NA', 'code' => '+264', 'name' => 'Namibia'],
        ['iso' => 'NC', 'code' => '+687', 'name' => 'New Caledonia'],
        ['iso' => 'NE', 'code' => '+227', 'name' => 'Niger'],
        ['iso' => 'NF', 'code' => '+672', 'name' => 'Norfolk Island'],
        ['iso' => 'NG', 'code' => '+234', 'name' => 'Nigeria'],
        ['iso' => 'NI', 'code' => '+505', 'name' => 'Nicaragua'],
        ['iso' => 'NL', 'code' => '+31', 'name' => 'Netherlands'],
        ['iso' => 'NO', 'code' => '+47', 'name' => 'Norway'],
        ['iso' => 'NP', 'code' => '+977', 'name' => 'Nepal'],
        ['iso' => 'NR', 'code' => '+674', 'name' => 'Nauru'],
        ['iso' => 'NU', 'code' => '+683', 'name' => 'Niue'],
        ['iso' => 'NZ', 'code' => '+64', 'name' => 'New Zealand'],
        ['iso' => 'OM', 'code' => '+968', 'name' => 'Oman'],
        ['iso' => 'PA', 'code' => '+507', 'name' => 'Panama'],
        ['iso' => 'PE', 'code' => '+51', 'name' => 'Peru'],
        ['iso' => 'PF', 'code' => '+689', 'name' => 'French Polynesia'],
        ['iso' => 'PG', 'code' => '+675', 'name' => 'Papua New Guinea'],
        ['iso' => 'PH', 'code' => '+63', 'name' => 'Philippines'],
        ['iso' => 'PK', 'code' => '+92', 'name' => 'Pakistan'],
        ['iso' => 'PL', 'code' => '+48', 'name' => 'Poland'],
        ['iso' => 'PM', 'code' => '+508', 'name' => 'Saint Pierre and Miquelon'],
        ['iso' => 'PN', 'code' => '+872', 'name' => 'Pitcairn Islands'],
        ['iso' => 'PR', 'code' => '+1939', 'name' => 'Puerto Rico'],
        ['iso' => 'PS', 'code' => '+970', 'name' => 'Palestine'],
        ['iso' => 'PT', 'code' => '+351', 'name' => 'Portugal'],
        ['iso' => 'PW', 'code' => '+680', 'name' => 'Palau'],
        ['iso' => 'PY', 'code' => '+595', 'name' => 'Paraguay'],
        ['iso' => 'QA', 'code' => '+974', 'name' => 'Qatar'],
        ['iso' => 'RE', 'code' => '+262', 'name' => 'Réunion'],
        ['iso' => 'RO', 'code' => '+40', 'name' => 'Romania'],
        ['iso' => 'RS', 'code' => '+381', 'name' => 'Serbia'],
        ['iso' => 'RU', 'code' => '+7', 'name' => 'Russia'],
        ['iso' => 'RW', 'code' => '+250', 'name' => 'Rwanda'],
        ['iso' => 'SA', 'code' => '+966', 'name' => 'Saudi Arabia'],
        ['iso' => 'SB', 'code' => '+677', 'name' => 'Solomon Islands'],
        ['iso' => 'SC', 'code' => '+248', 'name' => 'Seychelles'],
        ['iso' => 'SD', 'code' => '+249', 'name' => 'Sudan'],
        ['iso' => 'SE', 'code' => '+46', 'name' => 'Sweden'],
        ['iso' => 'SG', 'code' => '+65', 'name' => 'Singapore'],
        ['iso' => 'SH', 'code' => '+290', 'name' => 'Saint Helena'],
        ['iso' => 'SI', 'code' => '+386', 'name' => 'Slovenia'],
        ['iso' => 'SJ', 'code' => '+47', 'name' => 'Svalbard and Jan Mayen'],
        ['iso' => 'SK', 'code' => '+421', 'name' => 'Slovakia'],
        ['iso' => 'SL', 'code' => '+232', 'name' => 'Sierra Leone'],
        ['iso' => 'SM', 'code' => '+378', 'name' => 'San Marino'],
        ['iso' => 'SN', 'code' => '+221', 'name' => 'Senegal'],
        ['iso' => 'SO', 'code' => '+252', 'name' => 'Somalia'],
        ['iso' => 'SR', 'code' => '+597', 'name' => 'Suriname'],
        ['iso' => 'SS', 'code' => '+211', 'name' => 'South Sudan'],
        ['iso' => 'ST', 'code' => '+239', 'name' => 'São Tomé and Príncipe'],
        ['iso' => 'SV', 'code' => '+503', 'name' => 'El Salvador'],
        ['iso' => 'SX', 'code' => '+1721', 'name' => 'Sint Maarten'],
        ['iso' => 'SY', 'code' => '+963', 'name' => 'Syria'],
        ['iso' => 'SZ', 'code' => '+268', 'name' => 'Swaziland'],
        ['iso' => 'TC', 'code' => '+1649', 'name' => 'Turks and Caicos Islands'],
        ['iso' => 'TD', 'code' => '+235', 'name' => 'Chad'],
        ['iso' => 'TG', 'code' => '+228', 'name' => 'Togo'],
        ['iso' => 'TH', 'code' => '+66', 'name' => 'Thailand'],
        ['iso' => 'TJ', 'code' => '+992', 'name' => 'Tajikistan'],
        ['iso' => 'TK', 'code' => '+690', 'name' => 'Tokelau'],
        ['iso' => 'TL', 'code' => '+670', 'name' => 'East Timor'],
        ['iso' => 'TM', 'code' => '+993', 'name' => 'Turkmenistan'],
        ['iso' => 'TN', 'code' => '+216', 'name' => 'Tunisia'],
        ['iso' => 'TO', 'code' => '+676', 'name' => 'Tonga'],
        ['iso' => 'TR', 'code' => '+90', 'name' => 'Turkey'],
        ['iso' => 'TT', 'code' => '+1868', 'name' => 'Trinidad and Tobago'],
        ['iso' => 'TV', 'code' => '+688', 'name' => 'Tuvalu'],
        ['iso' => 'TW', 'code' => '+886', 'name' => 'Taiwan'],
        ['iso' => 'TZ', 'code' => '+255', 'name' => 'Tanzania'],
        ['iso' => 'UA', 'code' => '+380', 'name' => 'Ukraine'],
        ['iso' => 'UG', 'code' => '+256', 'name' => 'Uganda'],
        ['iso' => 'UMI', 'code' => '+246', 'name' => 'United States Minor Outlying Islands'],
        ['iso' => 'US', 'code' => '+1', 'name' => 'United States'],
        ['iso' => 'UY', 'code' => '+598', 'name' => 'Uruguay'],
        ['iso' => 'UZ', 'code' => '+998', 'name' => 'Uzbekistan'],
        ['iso' => 'VA', 'code' => '+379', 'name' => 'Holy See'],
        ['iso' => 'VC', 'code' => '+1784', 'name' => 'Saint Vincent and the Grenadines'],
        ['iso' => 'VE', 'code' => '+58', 'name' => 'Venezuela'],
        ['iso' => 'VG', 'code' => '+1284', 'name' => 'Virgin Islands (British)'],
        ['iso' => 'VI', 'code' => '+1340', 'name' => 'Virgin Islands (U.S.)'],
        ['iso' => 'VN', 'code' => '+84', 'name' => 'Vietnam'],
        ['iso' => 'VU', 'code' => '+678', 'name' => 'Vanuatu'],
        ['iso' => 'WF', 'code' => '+681', 'name' => 'Wallis and Futuna'],
        ['iso' => 'WS', 'code' => '+685', 'name' => 'Samoa'],
        ['iso' => 'XK', 'code' => '+383', 'name' => 'Republic of Kosovo'],
        ['iso' => 'YE', 'code' => '+967', 'name' => 'Yemen'],
        ['iso' => 'YT', 'code' => '+262', 'name' => 'Mayotte'],
        ['iso' => 'ZA', 'code' => '+27', 'name' => 'South Africa'],
        ['iso' => 'ZM', 'code' => '+260', 'name' => 'Zambia'],
        ['iso' => 'ZW', 'code' => '+263', 'name' => 'Zimbabwe'],
    ];

    /**
     * Handle the action
     */
    public function handle($params)
    {
        $this->name = data_get($params, 'name');
        $this->filters = data_get($params, 'filters');
        $this->selected = $this->filters ? (array)Arr::pull($this->filters, 'value') : [];
        $this->exclude = $this->filters ? (array)Arr::pull($this->filters, 'exclude') : [];

        $method = str($this->name)->camel()->toString();
        $options = method_exists($this, $method)
            ? $this->{$method}()
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
        return collect(self::DIAL_CODES)->map(fn ($item) => [
            'value' => $item['code'],
            'label' => $item['iso'].' ('.$item['code'].')',
        ])->toArray();
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
