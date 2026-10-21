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

                let { line, onLine } = chrome(this.$el)

                this.chart = new ApexCharts.default(this.$el, {
                    chart: {
                        type: 'area',
                        height: '100%',
                        sparkline: { enabled: true },
                    },
                    series: [{ 
                        data: config.data.map(data => ({
                            x: data.label,
                            y: data.value,
                        })),
                    }],
                    xaxis: {
                        type: 'category',
                    },
                    stroke: {
                        width: 1,
                        curve: 'smooth',
                    },
                    tooltip: {
                        custom: ({ dataPointIndex }) => tooltipHtml(config.data[dataPointIndex].tooltip),
                    },
                    ...(config.max?.value ? {
                        yaxis: {
                            min: (config.min?.value || 0) * 1.12,
                            max: config.max.value * 1.12,  // add buffer to yaxis to prevent annotation being cut off
                        },
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
                    } : {}),
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

            let { text, line, onLine } = chrome(this.$el)

            this.chart.updateOptions({
                colors: [seriesColor(config.color, 'accent', this.$el)],
                chart: { foreColor: text },
                xaxis: {
                    labels: {
                        style: {
                            colors: config.data.map(() => text),
                        },
                    },
                },
                ...config.max?.value ? {
                    yaxis: {
                        min: (config.min?.value || 0) * 1.12,
                        max: config.max.value * 1.12,  // add buffer to yaxis to prevent annotation being cut off
                    },
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
