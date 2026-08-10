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
function measureLabelBox(ctx, text) {
    ctx.font = '600 11px sans-serif';

    return {
        width: ctx.measureText(text).width + 10,
        height: 17,
    };
}

function drawValueLabel(ctx, text, x, y, { width, height }, { background, color }) {
    ctx.save();
    ctx.font = '600 11px sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    ctx.fillStyle = background;
    ctx.fillRect(x - width / 2, y - height / 2, width, height);

    ctx.fillStyle = color;
    ctx.fillText(text, x, y);
    ctx.restore();
}

const chartValueLabelsPlugin = {
    id: 'chartValueLabels',
    afterDatasetsDraw(chart) {
        const { ctx, chartArea } = chart;
        const isArc = ['doughnut', 'pie', 'polarArea'].includes(chart.config.type);
        const isDark = document.documentElement.classList.contains('dark');
        const horizontal = chart.options.indexAxis === 'y';
        const margin = 6;

        chart.data.datasets.forEach((dataset, datasetIndex) => {
            const meta = chart.getDatasetMeta(datasetIndex);
            if (meta.hidden) return;

            meta.data.forEach((element, index) => {
                const value = dataset.data[index];
                if (value === undefined || value === null || value === 0) return;

                try {
                    const position = element.tooltipPosition();
                    const text = String(value);

                    if (isArc) {
                        const box = measureLabelBox(ctx, text);
                        drawValueLabel(ctx, text, position.x, position.y, box, {
                            background: 'rgba(0, 0, 0, 0.45)',
                            color: '#ffffff',
                        });

                        return;
                    }

                    const box = measureLabelBox(ctx, text);
                    const outsideColors = {
                        background: isDark ? 'rgba(24, 24, 27, 0.85)' : 'rgba(255, 255, 255, 0.85)',
                        color: isDark ? '#e5e7eb' : '#374151',
                    };
                    const insideColors = { background: 'rgba(0, 0, 0, 0.35)', color: '#ffffff' };

                    let x, y, colors;

                    if (horizontal) {
                        const fitsOutside = position.x + margin + box.width <= chartArea.right;
                        x = fitsOutside
                            ? position.x + margin + box.width / 2
                            : position.x - margin - box.width / 2;
                        y = position.y;
                        colors = fitsOutside ? outsideColors : insideColors;
                    } else {
                        const fitsAbove = position.y - margin - box.height >= chartArea.top;
                        x = position.x;
                        y = fitsAbove
                            ? position.y - margin - box.height / 2
                            : position.y + margin + box.height / 2;
                        colors = fitsAbove ? outsideColors : insideColors;
                    }

                    drawValueLabel(ctx, text, x, y, box, colors);
                } catch {
                    // Ignora elementos sin posición calculable (p.ej. segmentos ocultos a mitad de animación).
                }
            });
        });
    },
};

window.filamentChartJsGlobalPlugins = [chartValueLabelsPlugin];
