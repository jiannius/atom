import { seriesColor, chrome, tooltipHtml } from './colors'
import chartFactory, { maxAnnotation } from './factory'

export default (config) => chartFactory({
    options: (component) => {
        let { line, onLine } = chrome(component.$el)

        return {
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
                annotations: maxAnnotation(config, { line, onLine }),
            } : {}),
        }
    },
    restyle: (component) => component.setColors(),
    restyleAfterRender: true,
}, {
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
                annotations: maxAnnotation(config, { line, onLine }),
            } : {}
        })
    },
})
