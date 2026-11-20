import arr from '../../helpers/arr'
import { seriesColor, chrome, tooltipHtml } from './colors'
import chartFactory, { maxAnnotation } from './factory'

export default (config) => chartFactory({
    options: () => ({
        chart: {
            type: 'bar',
            height: '100%',
            toolbar: { show: false },
        },
        series: [{
            data: arr.pluck(config.data, 'value'),
        }],
        plotOptions: {
            bar: {
                columnWidth: '85%',
                borderRadius: 2,
                borderRadiusApplication: 'end',
            },
        },
        dataLabels: {
            enabled: false,
        },
        legend: {
            show: false,
        },
        tooltip: {
            custom: ({ dataPointIndex }) => tooltipHtml(config.data[dataPointIndex].tooltip),
        },
        xaxis: {
            axisTicks: { show: false },
            axisBorder: { show: false },
            categories: arr.pluck(config.data, 'label'),
        },
        yaxis: {
            show: false,
            axisTicks: { show: false },
            axisBorder: { show: false },
        },
    }),
    restyle: (component) => component.setColors(),
    restyleAfterRender: true,
}, {
    setColors () {
        if (!this.chart) return

        let { text, grid, line, onLine } = chrome(this.$el)

        this.chart.updateOptions({
            colors: [seriesColor(config.color, 'accent', this.$el)],
            chart: { foreColor: text },
            grid: { borderColor: grid },
            xaxis: {
                labels: {
                    style: {
                        colors: config.data.map(() => text),
                    },
                },
            },
            ...config.max?.value ? { annotations: maxAnnotation(config, { line, onLine }) } : {}
        })
    },
})
