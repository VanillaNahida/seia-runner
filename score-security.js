(function () {
  "use strict";

  function createOperationStats() {
    return {
      jumpDown: 0,
      jumpUp: 0,
      duckDown: 0,
      duckUp: 0,
      keyDown: 0,
      keyUp: 0,
      pointerDown: 0,
      pointerUp: 0,
      buttonDown: 0,
      buttonUp: 0,
      firstAt: 0,
      lastAt: 0
    };
  }

  function simpleHash(str) {
    var hash = 0;
    for (var i = 0; i < str.length; i++) {
      var ch = str.charCodeAt(i);
      hash = ((hash << 5) - hash) + ch;
      hash = hash | 0;
    }
    return Math.abs(hash).toString(16);
  }

  function toHex(buffer) {
    var bytes = new Uint8Array(buffer);
    var hex = "";
    for (var i = 0; i < bytes.length; i += 1) {
      hex += bytes[i].toString(16).padStart(2, "0");
    }
    return hex;
  }

  function sha256Hex(value) {
    if (!window.crypto || !window.crypto.subtle || !window.TextEncoder) {
      return Promise.resolve(simpleHash(value));
    }

    return window.crypto.subtle.digest("SHA-256", new TextEncoder().encode(value)).then(toHex);
  }

  function createAntiCheat(options) {
    var game = options.game;
    var player = options.player;
    var getState = options.getState;

    function resetOperationStats() {
      game.opStats = createOperationStats();
    }

    function recordOperation(kind) {
      if (getState() !== "playing" || !game.opStats) {
        return;
      }

      var now = Date.now();
      if (game.opStats[kind] !== undefined) {
        game.opStats[kind] += 1;
      }
      if (game.opStats.firstAt === 0) {
        game.opStats.firstAt = now;
      }
      game.opStats.lastAt = now;
    }

    function buildOperationPayload() {
      var stats = game.opStats || {};
      var ordered = {
        jumpDown: stats.jumpDown || 0,
        jumpUp: stats.jumpUp || 0,
        duckDown: stats.duckDown || 0,
        duckUp: stats.duckUp || 0,
        keyDown: stats.keyDown || 0,
        keyUp: stats.keyUp || 0,
        pointerDown: stats.pointerDown || 0,
        pointerUp: stats.pointerUp || 0,
        buttonDown: stats.buttonDown || 0,
        buttonUp: stats.buttonUp || 0,
        firstOffsetMs: stats.firstAt > 0 ? Math.max(0, stats.firstAt - game.startedAt) : 0,
        lastOffsetMs: stats.lastAt > 0 ? Math.max(0, stats.lastAt - game.startedAt) : 0
      };
      return JSON.stringify(ordered);
    }

    function buildHashAnswersPayload() {
      return JSON.stringify(game.hashAnswers || []);
    }

    function buildCoordSamplesPayload() {
      return JSON.stringify(game.coordSamples || []);
    }

    function solveHashChallenge(index, elapsedMs) {
      if (!game.hashChallenge || game.hashSolving) {
        return;
      }

      game.hashSolving = true;

      var seed = game.hashChallenge.seed || "";
      var difficulty = game.hashChallenge.difficulty || 2;
      var maxNonce = game.hashChallenge.max_nonce || 200000;
      var prefix = "0".repeat(difficulty);
      var nonce = 0;

      function tryNonce() {
        if (getState() !== "playing" || !game.hashChallenge) {
          game.hashSolving = false;
          return;
        }

        var value = seed + "|" + index + "|" + elapsedMs + "|" + nonce;
        sha256Hex(value).then(function (hash) {
          if (hash.slice(0, difficulty) === prefix) {
            game.hashAnswers.push({
              index: index,
              elapsedMs: elapsedMs,
              nonce: nonce,
              hash: hash
            });
            game.hashSolving = false;
            return;
          }

          nonce += 1;
          if (nonce <= maxNonce) {
            setTimeout(tryNonce, 0);
          } else {
            game.hashSolving = false;
          }
        });
      }

      tryNonce();
    }

    function updateHashChallenge() {
      if (!game.hashChallenge || game.startedAt <= 0) {
        return;
      }

      var elapsedMs = Date.now() - game.startedAt;
      var intervalMs = game.hashChallenge.interval_ms || 1200;
      if (elapsedMs < game.nextHashChallengeAt || game.hashSolving) {
        return;
      }

      var index = game.hashAnswers.length;
      game.nextHashChallengeAt = elapsedMs + intervalMs;
      solveHashChallenge(index, elapsedMs);
    }

    function sampleCoordinates() {
      if (game.startedAt <= 0) {
        return;
      }

      var elapsedMs = Date.now() - game.startedAt;
      if (elapsedMs < game.nextCoordSampleAt) {
        return;
      }

      game.nextCoordSampleAt = elapsedMs + 250;
      game.coordSamples.push({
        t: elapsedMs,
        x: Math.round(player.x),
        y: Math.round(player.y),
        vy: Math.round(player.vy),
        grounded: player.grounded ? 1 : 0,
        ducking: player.ducking ? 1 : 0,
        score: game.score
      });

      if (game.coordSamples.length > 260) {
        game.coordSamples.shift();
      }
    }

    return {
      resetOperationStats: resetOperationStats,
      recordOperation: recordOperation,
      updateHashChallenge: updateHashChallenge,
      sampleCoordinates: sampleCoordinates,
      buildOperationPayload: buildOperationPayload,
      buildHashAnswersPayload: buildHashAnswersPayload,
      buildCoordSamplesPayload: buildCoordSamplesPayload
    };
  }

  function normalizeSubmitText(value, maxLength, pattern) {
    value = value.replace(/[\u0000-\u001F\u007F]/g, "").trim();
    if (value.length > maxLength) {
      return null;
    }
    return pattern.test(value) ? value : null;
  }

  function isValidSubmitTimeline(game) {
    if (!Number.isFinite(game.startedAt) || !Number.isFinite(game.endedAt)) {
      return false;
    }

    if (game.startedAt <= 0 || game.endedAt <= game.startedAt) {
      return false;
    }

    var elapsedMs = game.endedAt - game.startedAt;
    return elapsedMs >= 500 && elapsedMs <= 3600000;
  }

  function getUserFingerprint() {
    var comps = [];
    comps.push(navigator.userAgent || "");
    comps.push(navigator.language || "");
    comps.push(screen.width + "x" + screen.height);
    comps.push(screen.colorDepth);
    comps.push(new Date().getTimezoneOffset());
    comps.push(!!navigator.cookieEnabled);
    comps.push(navigator.platform || "");
    comps.push(navigator.hardwareConcurrency || 0);
    if (navigator.deviceMemory) { comps.push(navigator.deviceMemory); }
    return simpleHash(comps.join("|"));
  }

  function getUserDevice() {
    var ua = navigator.userAgent;
    if (/Windows/i.test(ua)) return "Windows";
    if (/Mac/i.test(ua)) return "Mac";
    if (/Android/i.test(ua)) return "Android";
    if (/iPhone|iPad|iPod/i.test(ua)) return "iOS";
    if (/Linux/i.test(ua)) return "Linux";
    return "Unknown";
  }

  function base64EncodeBytes(bytes) {
    var binary = "";
    for (var i = 0; i < bytes.length; i += 1) {
      binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
  }

  function encryptScorePayload(payload, nonce) {
    if (!window.crypto || !window.crypto.subtle || !window.TextEncoder) {
      return Promise.reject(new Error("crypto unavailable"));
    }

    var iv = window.crypto.getRandomValues(new Uint8Array(12));
    var encoder = new TextEncoder();

    return window.crypto.subtle.digest("SHA-256", encoder.encode(nonce)).then(function (keyBytes) {
      return window.crypto.subtle.importKey("raw", keyBytes, { name: "AES-GCM" }, false, ["encrypt"]);
    }).then(function (key) {
      return window.crypto.subtle.encrypt({ name: "AES-GCM", iv: iv }, key, encoder.encode(JSON.stringify(payload)));
    }).then(function (cipherBytes) {
      return {
        iv: base64EncodeBytes(iv),
        payload: base64EncodeBytes(new Uint8Array(cipherBytes))
      };
    });
  }

  function initScoreSubmitAndCheat(options) {
    var game = options.game;
    var antiCheat = options.antiCheat;
    var parseResponse = options.parseResponse;
    var showToast = options.showToast;
    var cheatSound = options.cheatSound;

    var titleClicks = 0;
    var cheatModal = document.getElementById("cheatModal");
    var cheatInput = document.getElementById("cheatInput");
    var cheatSubmit = document.getElementById("cheatSubmit");
    var cheatCancel = document.getElementById("cheatCancel");
    var cheatError = document.getElementById("cheatError");
    var titleEl = document.querySelector(".hud strong");
    var uploadScoreBtn = document.getElementById("uploadScoreBtn");
    var submitModal = document.getElementById("submitModal");
    var submitNickname = document.getElementById("submitNickname");
    var submitMessage = document.getElementById("submitMessage");
    var submitError = document.getElementById("submitError");
    var submitScoreVal = document.getElementById("submitScoreVal");
    var submitConfirm = document.getElementById("submitConfirm");
    var submitCancel = document.getElementById("submitCancel");
    var ipInfo = null;
    var cheatCodes = [];

    function isCheatModalOpen() {
      return !cheatModal.classList.contains("hidden");
    }

    function isSubmitModalOpen() {
      return !submitModal.classList.contains("hidden");
    }

    function isAnyModalOpen() {
      return isCheatModalOpen() || isSubmitModalOpen();
    }

    function closeCheatModal() {
      cheatModal.classList.add("hidden");
    }

    function closeSubmitModal() {
      submitModal.classList.add("hidden");
    }

    function openSubmitModal() {
      if (isCheatModalOpen()) return;

      if (game.invincible) {
        showToast("作弊模式下无法上传成绩");
        return;
      }

      if (game.score === 0 || game.token === "") {
        showToast("没有有效成绩数据，请先开始游戏！");
        return;
      }

      submitScoreVal.textContent = game.score;
      submitNickname.value = localStorage.getItem("seia-runner-nick") || "";
      submitMessage.value = localStorage.getItem("seia-runner-msg") || "";
      submitError.classList.add("hidden");
      submitModal.classList.remove("hidden");
      submitNickname.focus();
    }

    titleEl.style.cursor = "pointer";

    titleEl.addEventListener("click", function () {
      titleClicks += 1;

      if (titleClicks >= 7 && !game.invincible) {
        titleClicks = 0;
        cheatModal.classList.remove("hidden");
        cheatInput.value = "";
        cheatInput.focus();
      }
    });

    fetch("api/cheat_code.txt")
      .then(function (res) { return res.text(); })
      .then(function (text) {
        var lines = text.split("\n");
        lines.forEach(function (line) {
          var code = line.trim().toUpperCase();
          if (code !== "") {
            cheatCodes.push(code);
          }
        });
      })
      .catch(function () {});

    cheatSubmit.addEventListener("click", function () {
      var inputCode = cheatInput.value.trim().toUpperCase();

      if (inputCode !== "" && cheatCodes.indexOf(inputCode) !== -1) {
        game.invincible = true;
        closeCheatModal();
        cheatSound.currentTime = 0;
        cheatSound.play().catch(function () {});
        showToast("作弊成功！");
      } else {
        cheatError.classList.remove("hidden");
      }
    });

    cheatCancel.addEventListener("click", closeCheatModal);

    cheatInput.addEventListener("keydown", function (event) {
      if (event.key === "Enter") {
        event.preventDefault();
        cheatSubmit.click();
      } else if (event.key === "Escape") {
        closeCheatModal();
      }
    });

    cheatInput.addEventListener("input", function () {
      cheatError.classList.add("hidden");
    });

    fetch("https://game.xcnahida.cn/api/v1/ip")
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.code === 0 && data.data) {
          ipInfo = data;
        }
      })
      .catch(function () {});

    uploadScoreBtn.addEventListener("click", function () {
      if (isAnyModalOpen()) return;
      openSubmitModal();
    });

    submitCancel.addEventListener("click", function () {
      closeSubmitModal();
    });

    submitConfirm.addEventListener("click", function () {
      var nickname = normalizeSubmitText(submitNickname.value, 10, /^[\p{L}\p{N}_\-\s\u4e00-\u9fa5]{1,10}$/u);
      var message = normalizeSubmitText(submitMessage.value, 30, /^[\p{L}\p{N}_\-\s\u4e00-\u9fa5，。！？,.!?、:：()（）]*$/u);

      if (nickname === null) {
        submitError.textContent = "昵称只能包含中英文、数字、空格、下划线和短横线";
        submitError.classList.remove("hidden");
        return;
      }

      if (nickname.length > 10) {
        submitError.textContent = "昵称不能超过10个字";
        submitError.classList.remove("hidden");
        return;
      }

      if (message === null) {
        submitError.textContent = "留言包含不允许的字符";
        submitError.classList.remove("hidden");
        return;
      }

      if (!isValidSubmitTimeline(game)) {
        submitError.textContent = "游戏时间轴校验失败，请正常完成一局游戏后再上传";
        submitError.classList.remove("hidden");
        return;
      }

      submitConfirm.disabled = true;
      submitConfirm.textContent = "提交中...";
      submitError.classList.add("hidden");

      var scorePayload = {
        nickname: nickname,
        message: message,
        score: String(game.score),
        game_token: game.token,
        client_started_at: String(game.startedAt),
        client_ended_at: String(game.endedAt),
        client_elapsed_ms: String(game.endedAt - game.startedAt),
        operation_stats: antiCheat.buildOperationPayload(),
        hash_answers: antiCheat.buildHashAnswersPayload(),
        coord_samples: antiCheat.buildCoordSamplesPayload(),
        ip_addr: ipInfo ? ipInfo.data.addr || "" : "",
        device: getUserDevice(),
        fingerprint: getUserFingerprint(),
        location: ""
      };

      if (ipInfo && ipInfo.data) {
        var locParts = [];
        if (ipInfo.data.country) locParts.push(ipInfo.data.country);
        if (ipInfo.data.province) locParts.push(ipInfo.data.province);
        scorePayload.location = locParts.join("·");
      }

      fetch("api/score_nonce.php", {
        method: "GET",
        cache: "no-store",
        credentials: "same-origin"
      })
        .then(parseResponse)
        .then(function (nonceData) {
          if (nonceData.code !== 0 || !nonceData.data || !nonceData.data.nonce) {
            throw new Error("invalid score nonce");
          }

          return encryptScorePayload(scorePayload, nonceData.data.nonce).then(function (encrypted) {
            var formData = new URLSearchParams();
            formData.append("score_nonce", nonceData.data.nonce);
            formData.append("score_iv", encrypted.iv);
            formData.append("score_payload", encrypted.payload);

            return fetch("api/submit_score.php", {
              method: "POST",
              credentials: "same-origin",
              headers: {
                "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8"
              },
              body: formData.toString()
            });
          });
        })
        .then(parseResponse)
        .then(function (data) {
          submitConfirm.disabled = false;
          submitConfirm.textContent = "提交";

          if (data.code === 0) {
            game.token = "";
            uploadScoreBtn.classList.add("hidden");
            localStorage.setItem("seia-runner-nick", nickname);
            if (!data.data.oldScore) {
              localStorage.setItem("seia-runner-msg", message);
            }
            closeSubmitModal();
            if (data.data.updated) {
              showToast("成绩已更新！提高了 " + data.data.improved + " 分，当前排名第 " + data.data.rank + " 名");
            } else if (data.data.oldScore) {
              showToast(data.message);
            } else {
              showToast("上传成功！当前排名第 " + data.data.rank + " 名");
            }
          } else {
            submitError.textContent = "提交成绩失败：" + (data.message || "未知错误");
            submitError.classList.remove("hidden");
          }
        })
        .catch(function (err) {
          submitConfirm.disabled = false;
          submitConfirm.textContent = "提交";
          if (err.message !== "server 403 html response") {
            submitError.textContent = "提交成绩失败：数据库出错，请稍后重试";
            submitError.classList.remove("hidden");
          }
        });
    });

    submitNickname.addEventListener("keydown", function (event) {
      if (event.key === "Enter") {
        event.preventDefault();
        submitMessage.focus();
      } else if (event.key === "Escape") {
        closeSubmitModal();
      }
    });

    submitMessage.addEventListener("keydown", function (event) {
      if (event.key === "Enter") {
        event.preventDefault();
        submitConfirm.click();
      } else if (event.key === "Escape") {
        closeSubmitModal();
      }
    });

    return {
      isCheatModalOpen: isCheatModalOpen,
      isSubmitModalOpen: isSubmitModalOpen,
      isAnyModalOpen: isAnyModalOpen,
      openSubmitModal: openSubmitModal,
      closeSubmitModal: closeSubmitModal,
      closeCheatModal: closeCheatModal
    };
  }

  window.SeiaRunnerSecurity = {
    createOperationStats: createOperationStats,
    createAntiCheat: createAntiCheat,
    initScoreSubmitAndCheat: initScoreSubmitAndCheat
  };
}());
