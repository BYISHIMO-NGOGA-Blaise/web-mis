/* ============================================
   School Management System - Dashboard JS
   ============================================ */

document.addEventListener('DOMContentLoaded', function() {
    initAnimatedCounters();
    initScrollAnimations();
    initCurrentTime();
});

function initAnimatedCounters() {
    var counters = document.querySelectorAll('[data-counter]');
    counters.forEach(function(el) {
        var target = parseInt(el.getAttribute('data-counter'), 10);
        if (isNaN(target)) return;
        var duration = 1200;
        var start = 0;
        var startTime = null;

        function animate(timestamp) {
            if (!startTime) startTime = timestamp;
            var progress = Math.min((timestamp - startTime) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3);
            var current = Math.floor(eased * target);
            el.textContent = current.toLocaleString();
            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                el.textContent = target.toLocaleString();
            }
        }
        requestAnimationFrame(animate);
    });
}

function initScrollAnimations() {
    var elements = document.querySelectorAll('.card, .metric-card, .quick-action-item, .welcome-banner');
    elements.forEach(function(el, index) {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.5s ease ' + (index * 0.07) + 's, transform 0.5s ease ' + (index * 0.07) + 's';
        setTimeout(function() {
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        }, 50);
    });
}

function initCurrentTime() {
    var timeEl = document.getElementById('current-time');
    if (!timeEl) return;

    function updateTime() {
        var now = new Date();
        var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        timeEl.textContent = now.toLocaleDateString('en-US', options);
    }
    updateTime();
    setInterval(updateTime, 60000);
}

function initCharts(chartConfigs) {
    if (typeof Chart === 'undefined') return;

    Chart.defaults.font.family = "'Inter', 'Segoe UI', system-ui, sans-serif";
    Chart.defaults.font.weight = '600';

    chartConfigs.forEach(function(config) {
        var ctx = document.getElementById(config.canvasId);
        if (!ctx) return;

        new Chart(ctx.getContext('2d'), {
            type: config.type,
            data: config.data,
            options: Object.assign({
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: config.showLegend !== false,
                        position: 'bottom',
                        labels: {
                            padding: 16,
                            usePointStyle: true,
                            pointStyleWidth: 10,
                            font: { size: 12, weight: '600' }
                        }
                    }
                }
            }, config.options || {})
        });
    });
}
