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
// Usa un rectángulo sólido de fondo (en vez de sombra difuminada) para que el
// contraste se conserve también al exportar a PDF a través de impresoras virtuales,
// que no siempre reproducen fielmente los efectos de sombra del canvas.
function drawValueLabel(ctx, text, x, y, { background, color }) {
    ctx.save();
    ctx.font = '600 11px sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    const paddingX = 5;
    const paddingY = 3;
    const width = ctx.measureText(text).width + paddingX * 2;
    const height = 14 + paddingY;

    ctx.fillStyle = background;
    ctx.fillRect(x - width / 2, y - height / 2, width, height);

    ctx.fillStyle = color;
    ctx.fillText(text, x, y);
    ctx.restore();
}

const chartValueLabelsPlugin = {
    id: 'chartValueLabels',
    afterDatasetsDraw(chart) {
        const { ctx } = chart;
        const isArc = ['doughnut', 'pie', 'polarArea'].includes(chart.config.type);
        const isDark = document.documentElement.classList.contains('dark');
        const horizontal = chart.options.indexAxis === 'y';

        chart.data.datasets.forEach((dataset, datasetIndex) => {
            const meta = chart.getDatasetMeta(datasetIndex);
            if (meta.hidden) return;

            meta.data.forEach((element, index) => {
                const value = dataset.data[index];
                if (value === undefined || value === null || value === 0) return;

                try {
                    const position = element.tooltipPosition();

                    if (isArc) {
                        drawValueLabel(ctx, value, position.x, position.y, {
                            background: 'rgba(0, 0, 0, 0.45)',
                            color: '#ffffff',
                        });
                    } else {
                        const x = horizontal ? position.x + 18 : position.x;
                        const y = horizontal ? position.y : position.y - 12;
                        drawValueLabel(ctx, value, x, y, {
                            background: isDark ? 'rgba(24, 24, 27, 0.85)' : 'rgba(255, 255, 255, 0.85)',
                            color: isDark ? '#e5e7eb' : '#374151',
                        });
                    }
                } catch {
                    // Ignora elementos sin posición calculable (p.ej. segmentos ocultos a mitad de animación).
                }
            });
        });
    },
};

window.filamentChartJsGlobalPlugins = [chartValueLabelsPlugin];
