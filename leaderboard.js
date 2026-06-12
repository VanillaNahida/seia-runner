(function () {
  "use strict";

  var rankBody = document.getElementById("rankBody");
  var rankCount = document.getElementById("rankCount");

  loadScores();

  function loadScores() {
    fetch("api/get_scores.php")
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.code !== 0) {
          rankBody.innerHTML = '<tr><td colspan="7" class="rank-loading">加载失败</td></tr>';
          rankCount.textContent = "共 0 条记录";
          return;
        }

        renderTable(data.data);
      })
      .catch(function () {
        rankBody.innerHTML = '<tr><td colspan="7" class="rank-loading">加载失败，请稍后再试</td></tr>';
        rankCount.textContent = "共 0 条记录";
      });
  }

  function renderTable(rows) {
    if (!rows || rows.length === 0) {
      rankBody.innerHTML = '<tr><td colspan="7" class="rank-loading">暂无成绩记录</td></tr>';
      rankCount.textContent = "共 0 条记录";
      return;
    }

    rankCount.textContent = "共 " + rows.length + " 条记录";
    var html = "";

    rows.forEach(function (row, index) {
      var rank = index + 1;
      var rankClass = rank <= 3 ? " rank-top" : "";
      var nickname = escapeHtml(row.nickname);
      var message = row.message ? escapeHtml(row.message) : "-";
      var location = row.location || "-";
      var device = row.device || "-";
      var updateTime = formatTime(row.updated_at || row.created_at);

      html += "<tr>";
      html += '<td class="col-rank' + rankClass + '">' + rank + "</td>";
      html += '<td class="col-nick">' + nickname + "</td>";
      html += '<td class="col-score">' + padScore(row.score) + "</td>";
      html += '<td class="col-msg" title="' + message + '">' + message + "</td>";
      html += '<td class="col-loc">' + location + "</td>";
      html += '<td class="col-dev">' + device + "</td>";
      html += '<td class="col-time">' + updateTime + "</td>";
      html += "</tr>";
    });

    rankBody.innerHTML = html;
  }

  function formatTime(dateStr) {
    if (!dateStr) return "-";
    var d = new Date(dateStr.replace(" ", "T") + "+08:00");
    if (isNaN(d.getTime())) return dateStr;
    var year = d.getFullYear();
    var month = String(d.getMonth() + 1).padStart(2, "0");
    var day = String(d.getDate()).padStart(2, "0");
    var hours = String(d.getHours()).padStart(2, "0");
    var minutes = String(d.getMinutes()).padStart(2, "0");
    var seconds = String(d.getSeconds()).padStart(2, "0");
    return year + "-" + month + "-" + day + " " + hours + ":" + minutes + ":" + seconds;
  }

  function padScore(value) {
    return String(value).padStart(5, "0");
  }

  function escapeHtml(text) {
    return text
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }
})();
