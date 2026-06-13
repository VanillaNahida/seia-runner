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
      lastAt: 0,
      firstElapsedMs: 0,
      lastElapsedMs: 0
    };
  }

  function createRuntimeStats() {
    return {
      frameCount: 0,
      totalFrameMs: 0,
      maxFrameMs: 0,
      longFrames: 0,
      hiddenMs: 0,
      hiddenStartedAt: 0,
      visibilityChanges: 0,
      blurCount: 0,
      focusCount: 0,
      resizeCount: 0,
      suspiciousClockSkips: 0,
      minScore: 0,
      maxScore: 0,
      lastScore: 0
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

  function sha256FallbackHex(value) {
    function rightRotate(n, x) {
      return (x >>> n) | (x << (32 - n));
    }

    var mathPow = Math.pow;
    var maxWord = mathPow(2, 32);
    var lengthProperty = "length";
    var i;
    var j;
    var result = "";
    var words = [];
    var asciiBitLength = value[lengthProperty] * 8;
    var hash = [];
    var k = [];
    var primeCounter = 0;
    var isComposite = {};

    for (var candidate = 2; primeCounter < 64; candidate += 1) {
      if (!isComposite[candidate]) {
        for (i = 0; i < 313; i += candidate) {
          isComposite[i] = candidate;
        }
        hash[primeCounter] = (mathPow(candidate, 0.5) * maxWord) | 0;
        k[primeCounter] = (mathPow(candidate, 1 / 3) * maxWord) | 0;
        primeCounter += 1;
      }
    }

    value += "\x80";
    while (value[lengthProperty] % 64 - 56) {
      value += "\x00";
    }

    for (i = 0; i < value[lengthProperty]; i += 1) {
      j = value.charCodeAt(i);
      if (j >> 8) {
        return simpleHash(value);
      }
      words[i >> 2] |= j << ((3 - i) % 4) * 8;
    }
    words[words[lengthProperty]] = ((asciiBitLength / maxWord) | 0);
    words[words[lengthProperty]] = asciiBitLength;

    for (j = 0; j < words[lengthProperty];) {
      var w = words.slice(j, j += 16);
      var oldHash = hash.slice(0);

      for (i = 0; i < 64; i += 1) {
        var w15 = w[i - 15];
        var w2 = w[i - 2];
        var a = hash[0];
        var e = hash[4];
        var temp1 = hash[7]
          + (rightRotate(6, e) ^ rightRotate(11, e) ^ rightRotate(25, e))
          + ((e & hash[5]) ^ ((~e) & hash[6]))
          + k[i]
          + (w[i] = (i < 16) ? w[i] : (
            w[i - 16]
            + (rightRotate(7, w15) ^ rightRotate(18, w15) ^ (w15 >>> 3))
            + w[i - 7]
            + (rightRotate(17, w2) ^ rightRotate(19, w2) ^ (w2 >>> 10))
          ) | 0);
        var temp2 = (rightRotate(2, a) ^ rightRotate(13, a) ^ rightRotate(22, a))
          + ((a & hash[1]) ^ (a & hash[2]) ^ (hash[1] & hash[2]));

        hash = [(temp1 + temp2) | 0].concat(hash);
        hash[4] = (hash[4] + temp1) | 0;
      }

      for (i = 0; i < 8; i += 1) {
        hash[i] = (hash[i] + oldHash[i]) | 0;
      }
    }

    for (i = 0; i < 8; i += 1) {
      for (j = 3; j + 1; j -= 1) {
        var b = (hash[i] >> (j * 8)) & 255;
        result += ((b < 16) ? "0" : "") + b.toString(16);
      }
    }

    return result;
  }

  function sha256Hex(value) {
    if (!window.crypto || !window.crypto.subtle || !window.TextEncoder) {
      return Promise.resolve(sha256FallbackHex(value));
    }

    return window.crypto.subtle.digest("SHA-256", new TextEncoder().encode(value)).then(toHex);
  }

  function hmacSha256Hex(message, keyHex) {
    if (!window.crypto || !window.crypto.subtle || !window.TextEncoder || !/^[a-f0-9]{32}$/i.test(keyHex || "")) {
      return Promise.reject(new Error("crypto unavailable"));
    }

    var encoder = new TextEncoder();
    return window.crypto.subtle.importKey("raw", encoder.encode(keyHex), { name: "HMAC", hash: "SHA-256" }, false, ["sign"])
      .then(function (key) {
        return window.crypto.subtle.sign("HMAC", key, encoder.encode(message));
      })
      .then(toHex);
  }

  function createAntiCheat(options) {
    var game = options.game;
    var player = options.player;
    var getState = options.getState;

    function resetOperationStats() {
      game.opStats = createOperationStats();
      game.runtimeStats = createRuntimeStats();
    }

    function ensureRuntimeStats() {
      if (!game.runtimeStats) {
        game.runtimeStats = createRuntimeStats();
      }
      return game.runtimeStats;
    }

    function recordOperation(kind) {
      if (getState() !== "playing" || !game.opStats) {
        return;
      }

      var now = Date.now();
      var elapsedMs = Math.max(0, Math.round(game.elapsedMs || (now - game.startedAt)));
      if (game.opStats[kind] !== undefined) {
        game.opStats[kind] += 1;
      }
      if (game.opStats.firstAt === 0) {
        game.opStats.firstAt = now;
        game.opStats.firstElapsedMs = elapsedMs;
      }
      game.opStats.lastAt = now;
      game.opStats.lastElapsedMs = elapsedMs;
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
        firstOffsetMs: stats.firstElapsedMs || 0,
        lastOffsetMs: stats.lastElapsedMs || 0
      };
      return JSON.stringify(ordered);
    }

    function buildRuntimePayload() {
      var stats = ensureRuntimeStats();
      var hiddenMs = stats.hiddenMs || 0;
      if (getState() === "playing" && stats.hiddenStartedAt > 0) {
        hiddenMs += Math.max(0, Date.now() - stats.hiddenStartedAt);
      }

      return JSON.stringify({
        frameCount: stats.frameCount || 0,
        totalFrameMs: Math.round(stats.totalFrameMs || 0),
        maxFrameMs: Math.round(stats.maxFrameMs || 0),
        longFrames: stats.longFrames || 0,
        hiddenMs: Math.round(hiddenMs),
        visibilityChanges: stats.visibilityChanges || 0,
        blurCount: stats.blurCount || 0,
        focusCount: stats.focusCount || 0,
        resizeCount: stats.resizeCount || 0,
        suspiciousClockSkips: stats.suspiciousClockSkips || 0,
        minScore: stats.minScore || 0,
        maxScore: stats.maxScore || 0
      });
    }

    function recordFrame(dt) {
      if (getState() !== "playing") {
        return;
      }

      var stats = ensureRuntimeStats();
      var frameMs = Math.max(0, Math.round((dt || 0) * 1000));
      stats.frameCount += 1;
      stats.totalFrameMs += frameMs;
      stats.maxFrameMs = Math.max(stats.maxFrameMs, frameMs);
      if (frameMs > 80) {
        stats.longFrames += 1;
      }
      if (game.score < stats.lastScore) {
        stats.suspiciousClockSkips += 1;
      }
      stats.lastScore = game.score || 0;
      stats.maxScore = Math.max(stats.maxScore, game.score || 0);
      if (stats.minScore === 0 || (game.score > 0 && game.score < stats.minScore)) {
        stats.minScore = game.score;
      }
    }

    function recordRuntimeEvent(kind) {
      if (getState() !== "playing") {
        return;
      }

      var stats = ensureRuntimeStats();
      if (kind === "hidden") {
        stats.visibilityChanges += 1;
        if (stats.hiddenStartedAt === 0) {
          stats.hiddenStartedAt = Date.now();
        }
      } else if (kind === "visible") {
        stats.visibilityChanges += 1;
        if (stats.hiddenStartedAt > 0) {
          stats.hiddenMs += Math.max(0, Date.now() - stats.hiddenStartedAt);
          stats.hiddenStartedAt = 0;
        }
      } else if (kind === "blur") {
        stats.blurCount += 1;
      } else if (kind === "focus") {
        stats.focusCount += 1;
      } else if (kind === "resize") {
        stats.resizeCount += 1;
      }
    }

    function buildHashAnswersPayload() {
      return JSON.stringify(game.hashAnswers || []);
    }

    function waitForProofs(timeoutMs) {
      var startedAt = Date.now();

      return new Promise(function (resolve) {
        function check() {
          if (!game.hashSolving || Date.now() - startedAt >= timeoutMs) {
            resolve();
            return;
          }

          setTimeout(check, 50);
        }

        check();
      });
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

      var elapsedMs = Math.max(0, Math.round(game.elapsedMs || (Date.now() - game.startedAt)));
      var intervalMs = game.hashChallenge.interval_ms || 1200;
      if (elapsedMs < game.nextHashChallengeAt || game.hashSolving) {
        return;
      }

      var index = game.hashAnswers.length;
      game.nextHashChallengeAt = elapsedMs + intervalMs;
      solveHashChallenge(index, elapsedMs);
    }

    return {
      resetOperationStats: resetOperationStats,
      recordOperation: recordOperation,
      recordFrame: recordFrame,
      recordRuntimeEvent: recordRuntimeEvent,
      updateHashChallenge: updateHashChallenge,
      buildOperationPayload: buildOperationPayload,
      buildRuntimePayload: buildRuntimePayload,
      buildHashAnswersPayload: buildHashAnswersPayload,
      waitForProofs: waitForProofs
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

    var elapsedMs = Math.max(0, Math.round(game.elapsedMs || (game.endedAt - game.startedAt)));
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
    if (/Mac/i.test(ua)) return "MacOS";
    if (/Android/i.test(ua)) return "Android";
    if (/iPhone|iPad|iPod/i.test(ua)) return "iOS";
    if (/Linux/i.test(ua)) return "Linux";
    if (/OpenHarmony/i.test(ua)) return "HarmonyOS NEXT";
    if (/HarmonyOS/i.test(ua)) return "HarmonyOS";
    if (/ArkWeb/i.test(ua)) return "HarmonyOS";
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
    var nicknameMaxLen = 10;
    var messageMaxLen = 30;

    fetch("api/get_limits.php")
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data.code === 0 && data.data) {
          nicknameMaxLen = data.data.nickname_max_length;
          messageMaxLen = data.data.message_max_length;
          submitNickname.maxLength = nicknameMaxLen;
          submitNickname.setAttribute("placeholder", "昵称（最多" + nicknameMaxLen + "个字）");
          submitMessage.maxLength = messageMaxLen;
          submitMessage.setAttribute("placeholder", "留言（最多" + messageMaxLen + "个字，可选）");
        }
      })
      .catch(function() {});

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
      var nicknamePattern = new RegExp('^[\\p{L}\\p{N}_\\-\\s\\u4e00-\\u9fa5]{1,' + nicknameMaxLen + '}$', 'u');
      var messagePattern = /^[\p{L}\p{N}_\-\s\u4e00-\u9fa5，。！？,.!?、:：()（）]*$/u;
      var nickname = normalizeSubmitText(submitNickname.value, nicknameMaxLen, nicknamePattern);
      var message = normalizeSubmitText(submitMessage.value, messageMaxLen, messagePattern);

      if (nickname === null) {
        submitError.textContent = "昵称只能包含中英文、数字、空格、下划线和短横线";
        submitError.classList.remove("hidden");
        return;
      }

      if (nickname.length > nicknameMaxLen) {
        submitError.textContent = "昵称不能超过" + nicknameMaxLen + "个字";
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

      antiCheat.waitForProofs(800).then(function () {
        var clientElapsedMs = String(Math.max(500, Math.round(game.elapsedMs || (game.endedAt - game.startedAt))));
        var clientEndedAt = String(game.startedAt + Number(clientElapsedMs));
        var operationStats = antiCheat.buildOperationPayload();
        var runtimeStats = antiCheat.buildRuntimePayload();

        return Promise.all([
          hmacSha256Hex(String(game.score) + "|" + clientElapsedMs + "|" + operationStats, game.proofSalt),
          hmacSha256Hex("runtime|" + String(game.score) + "|" + clientElapsedMs + "|" + runtimeStats, game.proofSalt)
        ]).then(function (digests) {
          var scorePayload = {
            nickname: nickname,
            message: message,
            score: String(game.score),
            game_token: game.token,
            client_started_at: String(game.startedAt),
            client_ended_at: clientEndedAt,
            client_elapsed_ms: clientElapsedMs,
            operation_stats: operationStats,
            operation_stats_digest: digests[0],
            runtime_stats: runtimeStats,
            runtime_stats_digest: digests[1],
            hash_answers: antiCheat.buildHashAnswersPayload(),
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

        return fetch("api/score_nonce.php", {
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
            localStorage.setItem("seia-runner-msg", message);
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
    createRuntimeStats: createRuntimeStats,
    createAntiCheat: createAntiCheat,
    initScoreSubmitAndCheat: initScoreSubmitAndCheat
  };
}());
