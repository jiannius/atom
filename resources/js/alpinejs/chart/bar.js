export default (config) => {
    return {
        chart: null,

        colors: {
            red: '#fda4af',
            green: '#0f766e',
            orange: '#ea580c',
            gray: '#d4d4d8',
        },

        get isDarkMode () {
            return document.documentElement.classList.contains('dark')
        },

        init () {
            import('apexcharts').then(ApexCharts => {
                this.chart = new ApexCharts.default(this.$el, {
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
                    tooltip: {
                        custom: ({ series, seriesIndex, dataPointIndex, w }) => {
                            let data = config.data[dataPointIndex]
                            let tooltip = document.createElement('div')

                            tooltip.setAttribute('class', 'bg-zinc-800 text-white text-sm rounded-md px-3 py-1 shadow-lg')
                            tooltip.innerText = data.tooltip

                            return tooltip.outerHTML
                        },
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

            let dataColor = config.color || ''
            if (!dataColor || !dataColor.startsWith('#')) dataColor = this.colors[dataColor || 'gray']

            this.chart.updateOptions({
                colors: [dataColor],
                grid: { borderColor: this.isDarkMode ? '#52525D' : '#f4f4f5' },
                xaxis: {
                    labels: {
                        style: {
                            colors: config.data.map(() => (this.isDarkMode ? 'white' : 'black')),
                        },
                    },
                },
                ...config.max?.value ? {
                    annotations: {
                        yaxis: [{
                            y: config.max.value,
                            borderColor: this.isDarkMode ? 'white' : 'black',
                            label: {
                                borderColor: this.isDarkMode ? 'white' : 'black',
                                style: {
                                    color: this.isDarkMode ? 'black' : 'white',
                                    background: this.isDarkMode ? 'white' : 'black',
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