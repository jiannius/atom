export default (config) => {
    return {
        colors: {
            red: '#fda4af',
            green: '#0f766e',
            orange: '#ea580c',
            gray: '#d4d4d8',
        },

        init () {
            import('apexcharts').then(ApexCharts => {
                let chart = new ApexCharts.default(this.$el, {
                    chart: {
                        type: 'bar',
                        height: '100%',
                        toolbar: { show: false },
                    },
                    series: [{
                        data: config.data.pluck('value'),
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
                    colors: [this.getColor()],
                    tooltip: {
                        custom: ({ series, seriesIndex, dataPointIndex, w }) => {
                            let data = config.data[dataPointIndex]
                            let tooltip = document.createElement('div')

                            tooltip.setAttribute('class', 'bg-black/80 text-sm text-white rounded-md px-3 py-1 shadow-lg')
                            tooltip.innerText = data.tooltip
                                        
                            return tooltip.outerHTML
                        },
                    },
                    grid: {
                        borderColor: '#f4f4f5',
                    },
                    xaxis: {
                        axisTicks: { show: false },
                        axisBorder: { show: false },
                        categories: config.data.pluck('label'),
                    },
                    yaxis: {
                        show: false,
                        axisTicks: { show: false },
                        axisBorder: { show: false },
                    },
                    ...(config.max?.value ? {
                        annotations: {
                            yaxis: [{
                                y: config.max.value,
                                borderColor: 'black',
                                label: {
                                    borderColor: 'black',
                                    style: {
                                        color: 'white',
                                        background: 'black',
                                        fontSize: '12px',
                                    },
                                    position: 'center',
                                    text: config.max.label,
                                },
                            }],
                        },
                    } : {}),
                })

                setTimeout(() => chart.render(), 200)
            })
        },

        getColor () {
            let color = config.color || ''

            if (color.startsWith('#')) return color

            return this.colors[color || 'gray']
        },
    }
}