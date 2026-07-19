<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

describe('chart', function () {
    $barData = [['label' => 'Mon', 'value' => 10, 'tooltip' => '10 sales']];

    it('renders a bar chart by default', function () use ($barData) {
        $html = renderBlade('<atom:chart :data="$data"/>', ['data' => $barData]);

        expect($html)
            ->toContain('data-atom-chart')
            ->toContain('data-atom-chart-type="bar"')
            ->toContain('chartBar(')
            ->toContain('h-64');
    });

    it('maps type=area to the chartArea factory', function () use ($barData) {
        $html = renderBlade('<atom:chart type="area" :data="$data"/>', ['data' => $barData]);

        expect($html)
            ->toContain('data-atom-chart-type="area"')
            ->toContain('chartArea(')
            ->toContain('h-64');
    });

    it('maps type=trend to the chartTrend factory with a sparkline height', function () {
        $html = renderBlade('<atom:chart type="trend" :data="$data"/>', ['data' => [8, 12, 9, 14]]);

        expect($html)
            ->toContain('data-atom-chart-type="trend"')
            ->toContain('chartTrend(')
            ->toContain('h-16');
    });

    it('lets a caller override the default height', function () use ($barData) {
        $html = renderBlade('<atom:chart :data="$data" class="h-96"/>', ['data' => $barData]);

        expect($html)
            ->toContain('h-96')
            ->not->toContain('h-64');
    });

    it('serialises color, max and min into the x-data config', function () use ($barData) {
        $html = renderBlade(
            '<atom:chart :data="$data" color="green" :max="$max" :min="$min"/>',
            ['data' => $barData, 'max' => ['value' => 100, 'label' => 'Goal'], 'min' => ['value' => 0]],
        );

        expect($html)
            ->toContain('green')
            ->toContain('Goal')
            ->toContain('100');
    });

    it('is delegated to by card variant=chart', function () {
        $html = renderBlade(
            '<atom:card variant="chart" heading="Sales" :data="$data"/>',
            ['data' => [['label' => 'Mon', 'value' => 10, 'tooltip' => '10 sales']]],
        );

        expect($html)
            ->toContain('data-atom-chart')
            ->toContain('chartBar(')
            ->toContain('Sales'); // card still renders its own heading
    });
});
