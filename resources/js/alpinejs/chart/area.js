import { seriesColor, chrome, tooltipHtml } from './colors'

export default (config) => {
    return {
        chart: null,

        init () {
            import('apexcharts').then(ApexCharts => {
                let { line, onLine } = chrome()

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
                    this.chart.render()
                    this.setColors()
                })

                document.addEventListener('darkmode-changed', () => this.setColors())
            })
        },

        setColors () {
            if (!this.chart) return

            let { text, line, onLine } = chrome()

            this.chart.updateOptions({
                colors: [seriesColor(config.color, 'accent')],
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
