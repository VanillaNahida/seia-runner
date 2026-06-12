var currentPage = 1;
var currentType = 'all';
var currentQuery = '';
var currentPageSize = '10';

function getQueryParam(name) {
    var urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
}

function updateURL(type, page, query, pageSize) {
    var url = new URL(window.location);
    if (type) {
        url.searchParams.set('type', type);
    }
    if (page) {
        url.searchParams.set('page', page);
    }
    if (query) {
        url.searchParams.set('query', query);
        url.searchParams.delete('type');
    } else {
        url.searchParams.delete('query');
    }
    if (pageSize && pageSize !== '10') {
        url.searchParams.set('pageSize', pageSize);
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
            '<td class="col-nick">' + item.nickname + '</td>' +
            '<td class="col-score">' + item.score + '</td>' +
            '<td class="col-msg">' + (item.message || '') + '</td>' +
            '<td class="col-loc">' + item.location + '</td>' +
            '<td class="col-dev">' + item.device + '</td>' +
            '<td class="col-time">' + time + '</td>' +
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
            '<span class="rank-name">' + rank + suffix + ' ' + item.nickname + '</span>' +
            '<span class="rank-time">' + time + '</span>' +
            '</div>' +
            '<div class="rank-item-body">' +
            '<div class="rank-score">SCORE: <strong>' + item.score + '</strong></div>' +
            '<div class="rank-info">' + item.device + ' - ' + item.location + '</div>' +
            (item.message ? '<div class="rank-message">' + item.message + '</div>' : '') +
            '</div>' +
            '</div>';
    });

    rankList.innerHTML = html;
}

function renderPagination(total, pageSize, currentPage) {
    var pagination = document.getElementById('pagination');
    var totalPages = Math.ceil(total / pageSize);

    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }

    var html = '<ul class="pagination">';

    if (currentPage > 1) {
        html += '<li><a href="?type=' + currentType + '&pageSize=' + currentPageSize + '&page=' + (currentPage - 1) + (currentQuery ? '&query=' + encodeURIComponent(currentQuery) : '') + '">&laquo;</a></li>';
    } else {
        html += '<li class="disabled"><a href="#">&laquo;</a></li>';
    }

    for (var i = 1; i <= totalPages; i++) {
        if (i === currentPage) {
            html += '<li class="active"><a href="?type=' + currentType + '&pageSize=' + currentPageSize + '&page=' + i + (currentQuery ? '&query=' + encodeURIComponent(currentQuery) : '') + '">' + i + '</a></li>';
        } else {
            html += '<li><a href="?type=' + currentType + '&pageSize=' + currentPageSize + '&page=' + i + (currentQuery ? '&query=' + encodeURIComponent(currentQuery) : '') + '">' + i + '</a></li>';
        }
    }

    if (currentPage < totalPages) {
        html += '<li><a href="?type=' + currentType + '&pageSize=' + currentPageSize + '&page=' + (currentPage + 1) + (currentQuery ? '&query=' + encodeURIComponent(currentQuery) : '') + '">&raquo;</a></li>';
    } else {
        html += '<li class="disabled"><a href="#">&raquo;</a></li>';
    }

    html += '</ul>';
    pagination.innerHTML = html;
}

function loadData(type, page, pageSize, query) {
    var url = 'api/get_scores.php';
    var params = [];

    if (type) {
        params.push('type=' + type);
    }
    if (page) {
        params.push('page=' + page);
    }
    if (pageSize) {
        params.push('pageSize=' + pageSize);
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
                currentType = type || currentType;
                currentPage = page || currentPage;
                currentQuery = query || currentQuery;
                currentPageSize = pageSize || currentPageSize;

                document.getElementById('rankTitle').textContent =
                    (currentQuery ? '搜索: ' + currentQuery : '排行榜[' + getTypeLabel(currentType) + ']');

                document.getElementById('recordCount').textContent = '共 ' + data.total + ' 条记录';

                renderTable(data.data, data.total, data.page, data.pageSize);
                renderCards(data.data, data.total, data.page, data.pageSize);
                renderPagination(data.total, data.pageSize, data.page);
                updateNavActive();
            }
        })
        .catch(function() {
            document.getElementById('rankTableBody').innerHTML = '<tr><td colspan="7" class="rank-loading">加载失败</td></tr>';
            document.getElementById('rankList').innerHTML = '<div class="rank-item no-data"><div>加载失败</div></div>';
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
    var type = getQueryParam('type') || 'day';
    var page = parseInt(getQueryParam('page')) || 1;
    var query = getQueryParam('query') || '';
    var pageSize = getQueryParam('pageSize') || '10';

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