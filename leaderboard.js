var currentPage = 1;
var currentType = 'all';
var currentQuery = '';
var currentPageSize = '10';
var allowedTypes = ['day', 'week', 'month', 'all'];
var allowedPageSizes = ['10', '20', '50', '100', 'all'];

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

function buildPageHref(type, pageSize, page, query) {
    var params = new URLSearchParams();
    params.set('type', normalizeType(type));
    params.set('pageSize', normalizePageSize(pageSize));
    params.set('page', normalizePage(page));
    if (query) {
        params.set('query', query);
    }
    return '?' + params.toString();
}

function getQueryParam(name) {
    var urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
}

function updateURL(type, page, query, pageSize) {
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

function getRankSuffix(rank) {
    if (rank === 1) return 'st';
    if (rank === 2) return 'nd';
    if (rank === 3) return 'rd';
    return 'th';
}

function renderTable(data, total, page, pageSize) {
    var tableBody = document.getElementById('rankTableBody');

    if (data.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="7" class="rank-loading">暂无数据</td></tr>';
        return;
    }

    var html = '';
    var startRank = (page - 1) * pageSize + 1;

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

function renderCards(data, total, page, pageSize) {
    var rankList = document.getElementById('rankList');

    if (data.length === 0) {
        rankList.innerHTML = '<div class="rank-item no-data"><div>暂无数据</div></div>';
        return;
    }

    var html = '';
    var startRank = (page - 1) * pageSize + 1;

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
        html += '<li><a href="' + buildPageHref(safeType, safePageSize, currentPage - 1, currentQuery) + '">&laquo;</a></li>';
    } else {
        html += '<li class="disabled"><a href="#">&laquo;</a></li>';
    }

    for (var i = 1; i <= totalPages; i++) {
        if (i === currentPage) {
            html += '<li class="active"><a href="' + buildPageHref(safeType, safePageSize, i, currentQuery) + '">' + i + '</a></li>';
        } else {
            html += '<li><a href="' + buildPageHref(safeType, safePageSize, i, currentQuery) + '">' + i + '</a></li>';
        }
    }

    if (currentPage < totalPages) {
        html += '<li><a href="' + buildPageHref(safeType, safePageSize, currentPage + 1, currentQuery) + '">&raquo;</a></li>';
    } else {
        html += '<li class="disabled"><a href="#">&raquo;</a></li>';
    }

    html += '</ul>';
    pagination.innerHTML = html;
}

function loadData(type, page, pageSize, query) {
    var url = 'api/get_scores.php';
    var params = [];
    var safeType = normalizeType(type || currentType);
    var safePage = normalizePage(page || currentPage);
    var safePageSize = normalizePageSize(pageSize || currentPageSize);

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

    if (params.length > 0) {
        url += '?' + params.join('&');
    }

    fetch(url)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.code === 0) {
                currentType = safeType;
                currentPage = safePage;
                currentQuery = query || '';
                currentPageSize = safePageSize;

                document.getElementById('rankTitle').textContent =
                    (currentQuery ? '搜索: ' + currentQuery : '排行榜[' + getTypeLabel(currentType) + ']');

                document.getElementById('recordCount').textContent = '共 ' + data.total + ' 条记录';

                renderTable(data.data, data.total, data.page, data.pageSize);
                renderCards(data.data, data.total, data.page, data.pageSize);
                renderPagination(data.total, data.pageSize, data.page);
                updateNavActive();
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
    var pageSize = normalizePageSize(getQueryParam('pageSize') || '10');

    currentType = type;
    currentPage = page;
    currentQuery = query;
    currentPageSize = pageSize;

    if (query) {
        document.getElementById('searchInput').value = query;
    }

    document.getElementById('pageSizeSelect').value = pageSize;

    var navToggle = document.querySelector('.nav-toggle');
    var navMenu = document.querySelector('.nav-menu');

    navToggle.addEventListener('click', function() {
        navMenu.classList.toggle('active');
    });

    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');

    searchBtn.addEventListener('click', function() {
        var q = searchInput.value.trim();
        if (q) {
            loadData(null, 1, currentPageSize, q);
            updateURL(null, 1, q, currentPageSize);
        } else {
            loadData(currentType, 1, currentPageSize, '');
            updateURL(currentType, 1, '', currentPageSize);
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
            loadData(null, 1, newSize, currentQuery);
            updateURL(null, 1, currentQuery, newSize);
        } else {
            loadData(currentType, 1, newSize, '');
            updateURL(currentType, 1, '', newSize);
        }
    });

    loadData(type, page, pageSize, query);
}

document.addEventListener('DOMContentLoaded', init);
