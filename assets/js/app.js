$(function () {
    const SIDEBAR_KEY = 'tc_sidebar_icon_only';
    const body = $('body');
    const isDesktop = () => window.matchMedia('(min-width: 992px)').matches;

    // Restore preferred sidebar mode for desktop sessions.
    if (isDesktop() && localStorage.getItem(SIDEBAR_KEY) === '1') {
        body.addClass('sidebar-icon-only');
    }

    $('[data-toggle="minimize"]').on('click', function () {
        if (!isDesktop()) {
            localStorage.removeItem(SIDEBAR_KEY);
            return;
        }

        window.setTimeout(function () {
            localStorage.setItem(SIDEBAR_KEY, body.hasClass('sidebar-icon-only') ? '1' : '0');
        }, 0);
    });

    const sidebarLinks = document.querySelectorAll('#sidebar .nav-item:not(.nav-category) > .nav-link');
    if (window.bootstrap && window.bootstrap.Tooltip && sidebarLinks.length) {
        sidebarLinks.forEach(function (link) {
            const titleEl = link.querySelector('.menu-title');
            const title = titleEl ? titleEl.textContent.trim() : '';
            if (!title) {
                return;
            }

            link.setAttribute('title', title);
            link.setAttribute('aria-label', title);

            const tooltip = new window.bootstrap.Tooltip(link, {
                placement: 'right',
                trigger: 'hover',
                customClass: 'tc-sidebar-tooltip'
            });

            link.addEventListener('show.bs.tooltip', function (event) {
                if (!body.hasClass('sidebar-icon-only') || !isDesktop()) {
                    event.preventDefault();
                }
            });

            link.addEventListener('mouseleave', function () {
                tooltip.hide();
            });
        });
    }

    $('.datatable').each(function () {
        const $table = $(this);
        const $thead = $table.find('thead tr').first();
        const headerCount = $thead.find('th,td').length;
        const $bodyRows = $table.find('tbody tr');

        let emptyMessage = 'No records found';
        const $colspanRow = $bodyRows.filter(function () {
            const $cells = $(this).children('td,th');
            return $cells.length === 1 && Number($cells.attr('colspan') || 0) >= headerCount;
        }).first();

        if ($colspanRow.length) {
            emptyMessage = $.trim($colspanRow.text()) || emptyMessage;
            $colspanRow.remove();
        }

        const firstBodyCount = $table.find('tbody tr').first().children('td,th').length;
        if (firstBodyCount > 0 && firstBodyCount !== headerCount) {
            return;
        }

        $table.DataTable({
            pageLength: 10,
            order: [],
            responsive: true,
            language: {
                emptyTable: emptyMessage
            }
        });
    });
});
