var currentPage = 1;
var currentType = 'all';
var currentQuery = '';
var currentDate = '';
var currentPageSize = '10';
var allowedTypes = ['day', 'week', 'month', 'all'];
var allowedPageSizes = ['10', '20', '50', '100', 'all'];
var allData = [];
var sortState = { column: null, asc: true };
var currentTotal = 0;
var currentDataPageSize = 10;

function escapeHTML(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function normalizeType(type) {
    return allowedTypes.indexOf(type) !== -1 ? type : 'day';
}

function normalizePage(page) {
    var num = parseInt(page, 10);
    return isNaN(num) || num < 1 ? 1 : num;
}

function normalizePageSize(pageSize) {
    var str = String(pageSize || '10');
    return allowedPageSizes.indexOf(str) !== -1 ? str : '10';
}

function parseResponse(res) {
    if (res.status === 403) {
        var contentType = res.headers.get("Content-Type") || "";
        if (contentType.indexOf("text/html") !== -1) {
            return res.text().then(function (html) {
                showServerError(html);
                throw new Error("server 403 html response");
            });
        }
    }
    return res.json();
}

var serverErrorModal = document.getElementById("serverErrorModal");
var serverErrorFrame = document.getElementById("serverErrorFrame");
var serverErrorClose = document.getElementById("serverErrorClose");

serverErrorClose.addEventListener("click", function () {
    serverErrorModal.classList.add("hidden");
    serverErrorFrame.srcdoc = "";
});

serverErrorModal.addEventListener("click", function (e) {
    if (e.target === serverErrorModal) {
        serverErrorModal.classList.add("hidden");
        serverErrorFrame.srcdoc = "";
    }
});

function showServerError(html) {
    serverErrorFrame.srcdoc = html;
    serverErrorModal.classList.remove("hidden");
}

function buildPageHref(type, pageSize, page, query, date) {
    var params = new URLSearchParams();
    params.set('type', normalizeType(type));
    params.set('pageSize', normalizePageSize(pageSize));
    params.set('page', normalizePage(page));
    if (query) {
        params.set('query', query);
    }
    if (date) {
        params.set('date', date);
    }
    return '?' + params.toString();
}

function getQueryParam(name) {
    var urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
}

function updateURL(type, page, query, pageSize, date) {
    var url = new URL(window.location);
    if (type) {
        url.searchParams.set('type', normalizeType(type));
    }
    if (page) {
        url.searchParams.set('page', normalizePage(page));
    }
    if (query) {
        url.searchParams.set('query', query);
        url.searchParams.delete('type');
    } else {
        url.searchParams.delete('query');
    }
    var safePageSize = normalizePageSize(pageSize);
    if (safePageSize !== '10') {
        url.searchParams.set('pageSize', safePageSize);
    } else {
        url.searchParams.delete('pageSize');
    }
    if (date) {
        url.searchParams.set('date', date);
    } else {
        url.searchParams.delete('date');
    }
    window.history.replaceState({}, '', url);
}

function getTypeLabel(type) {
    var labels = {
        'day': '日',
        'week': '周',
        'month': '月',
        'all': '总'
    };
    return labels[type] || '总';
}

function formatTime(dateStr) {
    if (!dateStr) return '';
    var date = new Date(dateStr);
    var year = date.getFullYear();
    var month = String(date.getMonth() + 1).padStart(2, '0');
    var day = String(date.getDate()).padStart(2, '0');
    var hours = String(date.getHours()).padStart(2, '0');
    var minutes = String(date.getMinutes()).padStart(2, '0');
    var seconds = String(date.getSeconds()).padStart(2, '0');
    return year + '-' + month + '-' + day + ' ' + hours + ':' + minutes + ':' + seconds;
}

function sortDataArray(arr, column, asc) {
    var dir = asc ? 1 : -1;
    arr.sort(function(a, b) {
        var va, vb;
        switch (column) {
            case 'rank':
                va = a._rank || 0;
                vb = b._rank || 0;
                return (va - vb) * dir;
            case 'nickname':
                va = a.nickname || '';
                vb = b.nickname || '';
                return va.localeCompare(vb, 'zh-CN') * dir;
            case 'score':
                va = parseFloat(a.score) || 0;
                vb = parseFloat(b.score) || 0;
                return (va - vb) * dir;
            case 'message':
                va = a.message || '';
                vb = b.message || '';
                return va.localeCompare(vb, 'zh-CN') * dir;
            case 'location':
                va = a.location || '';
                vb = b.location || '';
                return va.localeCompare(vb, 'zh-CN') * dir;
            case 'device':
                va = a.device || '';
                vb = b.device || '';
                return va.localeCompare(vb, 'zh-CN') * dir;
            case 'updated_at':
                va = a.updated_at || a.created_at || '';
                vb = b.updated_at || b.created_at || '';
                return (new Date(va) - new Date(vb)) * dir;
            default:
                return 0;
        }
    });
}

function updateSortIndicators() {
    document.querySelectorAll('.rank-table th.sortable').forEach(function(th) {
        var arrow = th.querySelector('.sort-arrow');
        var col = th.getAttribute('data-column');
        if (col === sortState.column) {
            arrow.textContent = sortState.asc ? ' ▲' : ' ▼';
            th.classList.add('sort-active');
        } else {
            arrow.textContent = '';
            th.classList.remove('sort-active');
        }
    });
}

function getRankSuffix(rank) {
    if (rank === 1) return 'st';
    if (rank === 2) return 'nd';
    if (rank === 3) return 'rd';
    return 'th';
}

function renderTable(data, total, page, pageSize, incremental) {
    var tableBody = document.getElementById('rankTableBody');
    var startRank = (page - 1) * pageSize + 1;

    if (data.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="7" class="rank-loading">' + (currentDate ? '所筛选日期没有存在游玩记录' : '暂无数据') + '</td></tr>';
        return;
    }

    if (incremental) {
        tableBody.innerHTML = '';
        var idx = 0;
        var chunkSize = 150;
        function renderChunk() {
            var html = '';
            var end = Math.min(idx + chunkSize, data.length);
            for (; idx < end; idx++) {
                var item = data[idx];
                var rank = startRank + idx;
                var time = item.updated_at ? formatTime(item.updated_at) : formatTime(item.created_at);
                html += '<tr>' +
                    '<td class="col-rank">' + rank + getRankSuffix(rank) + '</td>' +
                    '<td class="col-nick">' + escapeHTML(item.nickname) + '</td>' +
                    '<td class="col-score">' + escapeHTML(item.score) + '</td>' +
                    '<td class="col-msg">' + escapeHTML(item.message || '') + '</td>' +
                    '<td class="col-loc">' + escapeHTML(item.location) + '</td>' +
                    '<td class="col-dev">' + escapeHTML(item.device) + '</td>' +
                    '<td class="col-time">' + escapeHTML(time) + '</td>' +
                    '</tr>';
            }
            tableBody.insertAdjacentHTML('beforeend', html);
            if (idx < data.length) {
                setTimeout(renderChunk, 0);
            }
        }
        renderChunk();
        return;
    }

    var html = '';

    data.forEach(function(item, index) {
        var rank = startRank + index;
        var suffix = getRankSuffix(rank);
        var time = item.updated_at ? formatTime(item.updated_at) : formatTime(item.created_at);

        html += '<tr>' +
            '<td class="col-rank">' + rank + suffix + '</td>' +
            '<td class="col-nick">' + escapeHTML(item.nickname) + '</td>' +
            '<td class="col-score">' + escapeHTML(item.score) + '</td>' +
            '<td class="col-msg">' + escapeHTML(item.message || '') + '</td>' +
            '<td class="col-loc">' + escapeHTML(item.location) + '</td>' +
            '<td class="col-dev">' + escapeHTML(item.device) + '</td>' +
            '<td class="col-time">' + escapeHTML(time) + '</td>' +
            '</tr>';
    });

    tableBody.innerHTML = html;
}

function renderCards(data, total, page, pageSize, incremental) {
    var rankList = document.getElementById('rankList');
    var startRank = (page - 1) * pageSize + 1;

    if (data.length === 0) {
        rankList.innerHTML = '<div class="rank-item no-data"><div>' + (currentDate ? '所筛选日期没有存在游玩记录' : '暂无数据') + '</div></div>';
        return;
    }

    if (incremental) {
        rankList.innerHTML = '';
        var idx = 0;
        var chunkSize = 50;
        function renderChunk() {
            var html = '';
            var end = Math.min(idx + chunkSize, data.length);
            for (; idx < end; idx++) {
                var item = data[idx];
                var rank = startRank + idx;
                var time = item.updated_at ? formatTime(item.updated_at) : formatTime(item.created_at);
                html += '<div class="rank-item">' +
                    '<div class="rank-item-header">' +
                    '<span class="rank-name">' + rank + getRankSuffix(rank) + ' ' + escapeHTML(item.nickname) + '</span>' +
                    '<span class="rank-time">' + escapeHTML(time) + '</span>' +
                    '</div>' +
                    '<div class="rank-item-body">' +
                    '<div class="rank-score">SCORE: <strong>' + escapeHTML(item.score) + '</strong></div>' +
                    '<div class="rank-info">' + escapeHTML(item.device) + ' - ' + escapeHTML(item.location) + '</div>' +
                    (item.message ? '<div class="rank-message">' + escapeHTML(item.message) + '</div>' : '') +
                    '</div>' +
                    '</div>';
            }
            rankList.insertAdjacentHTML('beforeend', html);
            if (idx < data.length) {
                setTimeout(renderChunk, 0);
            }
        }
        renderChunk();
        return;
    }

    var html = '';

    data.forEach(function(item, index) {
        var rank = startRank + index;
        var suffix = getRankSuffix(rank);
        var time = item.updated_at ? formatTime(item.updated_at) : formatTime(item.created_at);

        html += '<div class="rank-item">' +
            '<div class="rank-item-header">' +
            '<span class="rank-name">' + rank + suffix + ' ' + escapeHTML(item.nickname) + '</span>' +
            '<span class="rank-time">' + escapeHTML(time) + '</span>' +
            '</div>' +
            '<div class="rank-item-body">' +
            '<div class="rank-score">SCORE: <strong>' + escapeHTML(item.score) + '</strong></div>' +
            '<div class="rank-info">' + escapeHTML(item.device) + ' - ' + escapeHTML(item.location) + '</div>' +
            (item.message ? '<div class="rank-message">' + escapeHTML(item.message) + '</div>' : '') +
            '</div>' +
            '</div>';
    });

    rankList.innerHTML = html;
}

function renderPagination(total, pageSize, currentPage) {
    var pagination = document.getElementById('pagination');
    var totalPages = Math.ceil(total / pageSize);
    var safeType = normalizeType(currentType);
    var safePageSize = normalizePageSize(currentPageSize);

    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }

    var html = '<ul class="pagination">';

    if (currentPage > 1) {
        html += '<li><a href="' + buildPageHref(safeType, safePageSize, currentPage - 1, currentQuery, currentDate) + '">&laquo;</a></li>';
    } else {
        html += '<li class="disabled"><a href="#">&laquo;</a></li>';
    }

    var pagesToShow = [];
    var range = 2;

    pagesToShow.push(1);

    var pageStart = Math.max(2, currentPage - range);
    var pageEnd = Math.min(totalPages - 1, currentPage + range);

    if (pageStart > 2) {
        pagesToShow.push("...");
    }

    for (var i = pageStart; i <= pageEnd; i++) {
        pagesToShow.push(i);
    }

    if (pageEnd < totalPages - 1) {
        pagesToShow.push("...");
    }

    if (totalPages > 1) {
        pagesToShow.push(totalPages);
    }

    for (var j = 0; j < pagesToShow.length; j++) {
        var p = pagesToShow[j];
        if (p === "...") {
            html += '<li class="disabled"><a href="#">&hellip;</a></li>';
        } else if (p === currentPage) {
            html += '<li class="active"><a href="' + buildPageHref(safeType, safePageSize, p, currentQuery, currentDate) + '">' + p + '</a></li>';
        } else {
            html += '<li><a href="' + buildPageHref(safeType, safePageSize, p, currentQuery, currentDate) + '">' + p + '</a></li>';
        }
    }

    if (currentPage < totalPages) {
        html += '<li><a href="' + buildPageHref(safeType, safePageSize, currentPage + 1, currentQuery, currentDate) + '">&raquo;</a></li>';
    } else {
        html += '<li class="disabled"><a href="#">&raquo;</a></li>';
    }

    html += '</ul>';
    pagination.innerHTML = html;
}

function loadData(type, page, pageSize, query, date) {
    var url = 'api/get_scores.php';
    var params = [];
    var safeType = normalizeType(type || currentType);
    var safePage = normalizePage(page || currentPage);
    var safePageSize = normalizePageSize(pageSize || currentPageSize);
    var safeDate = date || currentDate;

    if (safeType) {
        params.push('type=' + encodeURIComponent(safeType));
    }
    if (safePage) {
        params.push('page=' + safePage);
    }
    if (safePageSize) {
        params.push('pageSize=' + encodeURIComponent(safePageSize));
    }
    if (query) {
        params.push('query=' + encodeURIComponent(query));
    }
    if (safeDate) {
        params.push('date=' + encodeURIComponent(safeDate));
    }

    if (params.length > 0) {
        url += '?' + params.join('&');
    }

    fetch(url)
        .then(parseResponse)
        .then(function(data) {
            if (data.code === 0) {
                currentType = safeType;
                currentPage = safePage;
                currentQuery = query || '';
                currentDate = safeDate;
                currentPageSize = safePageSize;
                currentTotal = data.total;
                currentDataPageSize = data.pageSize;

                document.getElementById('rankTitle').textContent =
                    (currentQuery ? '搜索: ' + currentQuery : '排行榜[' + getTypeLabel(currentType) + ']');

                document.getElementById('recordCount').textContent = '共 ' + data.total + ' 条记录';

                allData = data.data.map(function(item, index) {
                    item._rank = (data.page - 1) * data.pageSize + index + 1;
                    return item;
                });
                if (sortState.column) {
                    sortDataArray(allData, sortState.column, sortState.asc);
                }

                var isAllMode = safePageSize === 'all';
                renderTable(allData, data.total, data.page, data.pageSize, isAllMode);
                renderCards(allData, data.total, data.page, data.pageSize, isAllMode);
                renderPagination(data.total, data.pageSize, data.page);
                updateNavActive();
                updateSortIndicators();
            } else {
                document.getElementById('rankTableBody').innerHTML = '<tr><td colspan="7" class="rank-loading">' + (data.message || '查询失败，请稍后重试') + '</td></tr>';
                document.getElementById('rankList').innerHTML = '<div class="rank-item no-data"><div>' + (data.message || '查询失败，请稍后重试') + '</div></div>';
            }
        })
        .catch(function() {
            document.getElementById('rankTableBody').innerHTML = '<tr><td colspan="7" class="rank-loading">查询失败，请稍后重试</td></tr>';
            document.getElementById('rankList').innerHTML = '<div class="rank-item no-data"><div>查询失败，请稍后重试</div></div>';
        });
}

function updateNavActive() {
    var navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(function(item) {
        item.classList.remove('active');
    });

    if (currentQuery) {
        return;
    }

    var activeItem = document.querySelector('.nav-item[href="?type=' + currentType + '"]');
    if (activeItem) {
        activeItem.classList.add('active');
    }
}

function init() {
    var type = normalizeType(getQueryParam('type') || 'day');
    var page = normalizePage(getQueryParam('page') || 1);
    var query = getQueryParam('query') || '';
    var date = getQueryParam('date') || '';
    var pageSize = normalizePageSize(getQueryParam('pageSize') || '10');

    currentType = type;
    currentPage = page;
    currentQuery = query;
    currentDate = date;
    currentPageSize = pageSize;

    if (query) {
        document.getElementById('searchInput').value = query;
    }

    document.getElementById('pageSizeSelect').value = pageSize;
    if (date) {
        document.getElementById('datePicker').value = date;
    }

    var navToggle = document.querySelector('.nav-toggle');
    var navMenu = document.querySelector('.nav-menu');

    navToggle.addEventListener('click', function() {
        navMenu.classList.toggle('active');
    });

    // 导航栏点击时保留 date 参数
    document.querySelectorAll('.nav-menu .nav-item[href^="?type="]').forEach(function(a) {
        a.addEventListener('click', function(e) {
            if (currentDate) {
                e.preventDefault();
                var url = new URL(a.href, window.location.origin);
                url.searchParams.set('date', currentDate);
                window.location.href = url.toString();
            }
        });
    });

    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');

    searchBtn.addEventListener('click', function() {
        var q = searchInput.value.trim();
        if (q) {
            loadData(null, 1, currentPageSize, q, currentDate);
            updateURL(null, 1, q, currentPageSize, currentDate);
        } else {
            loadData(currentType, 1, currentPageSize, '', currentDate);
            updateURL(currentType, 1, '', currentPageSize, currentDate);
        }
    });

    searchInput.addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            searchBtn.click();
        }
    });

    var pageSizeSelect = document.getElementById('pageSizeSelect');
    pageSizeSelect.addEventListener('change', function() {
        var newSize = pageSizeSelect.value;
        currentPageSize = newSize;
        if (currentQuery) {
            loadData(null, 1, newSize, currentQuery, currentDate);
            updateURL(null, 1, currentQuery, newSize, currentDate);
        } else {
            loadData(currentType, 1, newSize, '', currentDate);
            updateURL(currentType, 1, '', newSize, currentDate);
        }
    });

    var datePicker = document.getElementById('datePicker');
    datePicker.addEventListener('change', function() {
        var d = datePicker.value;
        currentDate = d;
        if (currentQuery) {
            loadData(null, 1, currentPageSize, currentQuery, d);
            updateURL(null, 1, currentQuery, currentPageSize, d);
        } else {
            loadData(currentType, 1, currentPageSize, '', d);
            updateURL(currentType, 1, '', currentPageSize, d);
        }
    });

    // 排序：点击表头
    document.querySelector('.rank-table thead').addEventListener('click', function(e) {
        var th = e.target.closest('th.sortable');
        if (!th) return;
        var column = th.getAttribute('data-column');
        if (sortState.column === column) {
            sortState.asc = !sortState.asc;
        } else {
            sortState.column = column;
            sortState.asc = true;
        }
        sortDataArray(allData, sortState.column, sortState.asc);
        var pageSize = currentDataPageSize;
        renderTable(allData, currentTotal, currentPage, pageSize, currentPageSize === 'all');
        renderCards(allData, currentTotal, currentPage, pageSize, currentPageSize === 'all');
        updateSortIndicators();
    });
    
    // 桌面端表格留言点击展开/收起
    document.getElementById('rankTableBody').addEventListener('click', function(e) {
        var target = e.target.closest('.col-msg');
        if (target) {
            target.classList.toggle('expanded');
        }
    });

    // 手机端卡片留言点击展开/收起
    document.getElementById('rankList').addEventListener('click', function(e) {
        var target = e.target.closest('.rank-message');
        if (target) {
            target.classList.toggle('expanded');
        }
    });

    loadData(type, page, pageSize, query, date);
}

document.addEventListener('DOMContentLoaded', init);
