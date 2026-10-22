import arr from '../../helpers/arr'
import { seriesColor, chrome, tooltipHtml } from './colors'

export default (config) => {
    // the theme listener belongs to the document, so it is kept here to be taken off again when the element goes (destroy())
    let onTheme = null
    let rendered = false
    let destroyed = false

    return {
        chart: null,

        init () {
            import('apexcharts').then(ApexCharts => {
                // the element went before the library arrived
                if (destroyed || !this.$el.isConnected) return

                this.chart = new ApexCharts.default(this.$el, {
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
                })

                this.$nextTick(() => {
                    if (!this.$el.isConnected) return

                    this.chart.render()
                    rendered = true
                    this.setColors()
                })

                onTheme = () => {
                    if (this.$el.isConnected && rendered) this.setColors()
                }

                document.addEventListener('darkmode-changed', onTheme)
            })
        },

        destroy () {
            destroyed = true
            if (onTheme) document.removeEventListener('darkmode-changed', onTheme)

            onTheme = null
            rendered = false
        },

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
                ...config.max?.value ? {
                    annotations: {
                        yaxis: [{
                            y: config.max.value,
                            borderColor: line,
                            label: {
                                borderColor: line,
                                style: {
                                    color: onLine,
                                    background: line,
                                    fontSize: '12px',
                                },
                                position: 'center',
                                text: config.max.label,
                            },
                        }],
                    },
                } : {}
            })
        },
    }
}
