import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';

// Exponer L globalmente para usarlo en @script de Blade
window.L = L;

import SignaturePad from 'signature_pad';
window.SignaturePad = SignaturePad;

// Dibuja el valor numérico sobre cada barra/segmento de las gráficas del panel.
const chartValueLabelsPlugin = {
    id: 'chartValueLabels',
    afterDatasetsDraw(chart) {
        const { ctx } = chart;
        const isArc = ['doughnut', 'pie', 'polarArea'].includes(chart.config.type);
        const isDark = document.documentElement.classList.contains('dark');

        chart.data.datasets.forEach((dataset, datasetIndex) => {
            const meta = chart.getDatasetMeta(datasetIndex);
            if (meta.hidden) return;

            meta.data.forEach((element, index) => {
                const value = dataset.data[index];
                if (value === undefined || value === null || value === 0) return;

                const position = element.tooltipPosition();

                ctx.save();
                ctx.font = '600 11px sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';

                if (isArc) {
                    ctx.fillStyle = '#ffffff';
                    ctx.shadowColor = 'rgba(0, 0, 0, 0.6)';
                    ctx.shadowBlur = 3;
                    ctx.fillText(value, position.x, position.y);
                } else {
                    const horizontal = chart.options.indexAxis === 'y';
                    ctx.fillStyle = isDark ? '#e5e7eb' : '#374151';
                    ctx.textAlign = horizontal ? 'left' : 'center';
                    const x = horizontal ? position.x + 6 : position.x;
                    const y = horizontal ? position.y : position.y - 10;
                    ctx.fillText(value, x, y);
                }

                ctx.restore();
            });
        });
    },
};

window.filamentChartJsGlobalPlugins = [chartValueLabelsPlugin];
