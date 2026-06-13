(function () {
  "use strict";

  var canvas = document.getElementById("game");
  var ctx = canvas.getContext("2d");
  var scoreEl = document.getElementById("score");
  var bestEl = document.getElementById("best");
  var overlay = document.getElementById("overlay");
  var overlayText = document.getElementById("overlayText");
  var startButton = document.getElementById("startButton");
  var jumpButton = document.getElementById("jumpButton");
  var duckButton = document.getElementById("duckButton");

  var W = canvas.width;
  var H = canvas.height;
  var groundY = 284;
  var gravity = 2350;
  var jumpVelocity = -820;
  var jumpHoldForce = -1450;
  var maxJumpHold = 0.18;
  var fastDropGravity = 3900;
  var bestScore = Number(localStorage.getItem("seia-runner-best") || 0);
  var lastTime = 0;
  var spawnTimer = 0;
  var state = "ready";
  var input = {
    jumpHeld: false,
    duckHeld: false
  };
  var bgmList = [
    { name: "Peaceful_Day", file: "assets/music/bgm_Peaceful_Day.mp3" },
    { name: "张雪峰老师我还记得你😭", file: "assets/music/bgm.mp3" },
    { name: "熊大快跑BGM 1", file: "assets/music/bgm_xdkp.mp3" },
    { name: "你说你有点难追", file: "assets/music/%E9%87%91%E7%94%9F%20-%20%E5%91%8A%E7%99%BD%E6%B0%94%E7%90%83%20%28%E5%8F%98%E9%80%9F%29.mp3" },
    { name: "熊大快跑BGM 2", file: "assets/music/%E9%BA%A6%E4%B9%90%E8%BF%AAShop%20-%20%E7%86%8A%E5%A4%A7%E5%BF%AB%E8%B7%91BGM.mp3" }
  ];
  var bgmIndex = Number(localStorage.getItem("seia-runner-bgm-index") || 0);
  var bgm = new Audio(bgmList[bgmIndex].file);
  bgm.loop = true;
  bgm.volume = 0.5;
  var bgmButton = document.getElementById("bgmButton");
   bgmButton.textContent = "BGM: " + bgmList[bgmIndex].name;

  // 页面隐藏时暂停音乐，返回时恢复
  document.addEventListener("visibilitychange", function () {
    if (document.hidden) {
      bgm.pause();
    } else if (state === "playing") {
      bgm.play().catch(function () {});
    }
  });

  // 页面即将离开时暂停音乐
  window.addEventListener("pagehide", function () {
    bgm.pause();
  });
  var deathSound = new Audio("assets/audio/seia-death.wav");
  deathSound.volume = 0.9;
  var cheatSound = new Audio("assets/audio/0d000721.mp3");
  cheatSound.volume = 0.8;

  var assets = {
    runner: "assets/img/seia-runner.png",
    duck: "assets/img/seia-duck.png",
    qiaolezi: "assets/img/qiaolezi.png",
    qiaoleziAlt: "assets/img/qiaolezi-alt.png",
    spriteBottle: "assets/img/sprite-bottle.png"
  };

  var images = {};
  var loadedCount = 0;
  var assetKeys = Object.keys(assets);

  var player = {
    x: 106,
    y: groundY - 132,
    standW: 98,
    standH: 132,
    duckW: 104,
    duckH: 91,
    w: 98,
    h: 132,
    vy: 0,
    grounded: true,
    ducking: false,
    jumpHold: 0
  };

  var jumpBuffer = 0;
  var jumpBufferMax = 0.1;

  var game = {
    speed: 410,
    distance: 0,
    score: 0,
    obstacles: [],
    dust: [],
    invincible: false
  };

  function loadAssets() {
    assetKeys.forEach(function (key) {
      var img = new Image();
      img.onload = function () {
        loadedCount += 1;
        if (loadedCount === assetKeys.length) {
          configurePlayerSprites();
          updatePlayerShape();
          drawFrame(0);
        }
      };
      img.src = assets[key];
      images[key] = img;
    });
  }

  function configurePlayerSprites() {
    player.standH = 132;
    player.standW = Math.round(player.standH * images.runner.naturalWidth / images.runner.naturalHeight);
    player.duckH = 91;
    player.duckW = Math.round(player.duckH * images.duck.naturalWidth / images.duck.naturalHeight);
  }

  function resetGame() {
    input.jumpHeld = false;
    input.duckHeld = false;
    player.w = player.standW;
    player.h = player.standH;
    player.y = groundY - player.h;
    player.vy = 0;
    player.grounded = true;
    player.ducking = false;
    player.jumpHold = 0;
    game.speed = 410;
    game.distance = 0;
    game.score = 0;
    game.obstacles = [];
    game.dust = [];
    jumpBuffer = 0;
    spawnTimer = 0.65;
    lastTime = performance.now();
  }

  function startGame() {
    if (loadedCount !== assetKeys.length) {
      overlayText.textContent = "圣娅正在赶过来，稍等一会马上就能跑。";
      return;
    }

    playBgm();
    resetGame();
    state = "playing";
    overlay.classList.add("hidden");
    requestAnimationFrame(loop);
  }

  function playBgm() {
    if (!bgm.paused) {
      return;
    }

    bgm.play().catch(function () {
      // Browsers only allow audio after a user gesture; the next key/tap retries.
    });
  }

  function switchBgm() {
    bgm.pause();
    bgmIndex = (bgmIndex + 1) % bgmList.length;
    localStorage.setItem("seia-runner-bgm-index", String(bgmIndex));
    bgm = new Audio(bgmList[bgmIndex].file);
    bgm.loop = true;
    bgm.volume = 0.5;
    bgmButton.textContent = "BGM: " + bgmList[bgmIndex].name;
    if (state === "playing") {
      bgm.play().catch(function () {});
    }
  }

  bgmButton.addEventListener("click", function (e) {
    e.preventDefault();
    switchBgm();
  });

  var helpButton = document.getElementById("helpButton");
  var helpModal = document.getElementById("helpModal");
  var helpClose = document.getElementById("helpClose");

  helpButton.addEventListener("click", function (e) {
    e.preventDefault();
    helpModal.classList.remove("hidden");
  });

  helpClose.addEventListener("click", function () {
    helpModal.classList.add("hidden");
  });

  helpModal.addEventListener("click", function (e) {
    if (e.target === helpModal) {
      helpModal.classList.add("hidden");
    }
  });

  function jump() {
    if (state === "ready" || state === "gameover") {
      startGame();
      return;
    }

    if (player.grounded) {
      doJump();
    } else {
      jumpBuffer = jumpBufferMax;
    }
  }

  function doJump() {
    input.duckHeld = false;
    player.ducking = false;
    player.vy = jumpVelocity;
    player.grounded = false;
    player.jumpHold = maxJumpHold;
    jumpBuffer = 0;
    makeDust(player.x + 34, groundY - 8);
  }

  function setDuck(ducking) {
    input.duckHeld = ducking;

    if (state !== "playing") {
      return;
    }

    if (!player.grounded && ducking && player.vy < 900) {
      player.vy += 420;
    }
  }

  function endGame() {
    if (state === "gameover") {
      return;
    }

    state = "gameover";
    playDeathSound();
    bestScore = Math.max(bestScore, game.score);
    localStorage.setItem("seia-runner-best", String(bestScore));
    updateScore();
    overlayText.textContent = "圣娅：你跑不过我你信吗！按空格 / ↑ / 点击再跑一把。";
    startButton.textContent = "重来";
    if (!game.invincible) {
      uploadScoreBtn.classList.remove("hidden");
    } else {
      uploadScoreBtn.classList.add("hidden");
    }
    overlay.classList.remove("hidden");
  }

  function playDeathSound() {
    deathSound.currentTime = 0;
    deathSound.play().catch(function () {
      // If the browser blocks audio, the game can still end normally.
    });
  }

  function makeDust(x, y) {
    for (var i = 0; i < 6; i += 1) {
      game.dust.push({
        x: x - Math.random() * 18,
        y: y + Math.random() * 10,
        r: 2 + Math.random() * 4,
        vx: -80 - Math.random() * 120,
        life: 0.35 + Math.random() * 0.22
      });
    }
  }

  function spawnObstacle() {
    var flying = game.score > 220 && Math.random() < 0.35;
    var useAlt = Math.random() < 0.5;
    var obstacle;

    if (flying) {
      var heightRoll = Math.random();
      var lane;
      var speedMultiplier;

      if (heightRoll < 0.45) {
        lane = "duck";
      } else if (heightRoll < 0.78) {
        lane = "jump";
      } else {
        lane = "high";
      }

      if (lane === "duck") {
        speedMultiplier = 1.04 + Math.random() * 0.28;
      } else if (lane === "jump") {
        speedMultiplier = 0.94 + Math.random() * 0.22;
      } else {
        speedMultiplier = 1.12 + Math.random() * 0.38;
      }

      obstacle = {
        type: "spriteBottle",
        img: images.spriteBottle,
        x: W + 30,
        y: getFlyingY(lane),
        w: 134,
        h: 76,
        hitPad: 16,
        lane: lane,
        speedMultiplier: speedMultiplier
      };
    } else {
      obstacle = {
        type: useAlt ? "qiaoleziAlt" : "qiaolezi",
        img: useAlt ? images.qiaoleziAlt : images.qiaolezi,
        x: W + 30,
        y: groundY - (useAlt ? 112 : 118),
        w: useAlt ? 66 : 72,
        h: useAlt ? 134 : 144,
        hitPad: 11,
        speedMultiplier: 1
      };
    }

    game.obstacles.push(obstacle);
    spawnTimer = 0.92 + Math.random() * 0.78 - Math.min(game.score / 3200, 0.32);
  }

  function getFlyingY(lane) {
    if (lane === "duck") {
      return groundY - 166 - Math.random() * 12;
    }

    if (lane === "jump") {
      return groundY - 96 - Math.random() * 16;
    }

    return groundY - 222 - Math.random() * 26;
  }

  function update(dt) {
    updatePlayerShape();

    if (jumpBuffer > 0) {
      jumpBuffer -= dt;
    }

    if (input.jumpHeld && player.jumpHold > 0 && player.vy < 0 && !input.duckHeld) {
      player.vy += jumpHoldForce * dt;
      player.jumpHold -= dt;
    } else {
      player.jumpHold = 0;
    }

    player.vy += (input.duckHeld && !player.grounded ? fastDropGravity : gravity) * dt;
    player.y += player.vy * dt;

    if (player.y >= groundY - player.h) {
      player.y = groundY - player.h;
      player.vy = 0;
      player.grounded = true;
      player.jumpHold = 0;
      updatePlayerShape();

      if (jumpBuffer > 0) {
        doJump();
      }
    }

    game.speed = Math.min(720, game.speed + 7.5 * dt);
    game.distance += game.speed * dt;
    game.score = Math.floor(game.distance / 10);
    spawnTimer -= dt;

    if (spawnTimer <= 0) {
      spawnObstacle();
    }

    game.obstacles.forEach(function (obstacle) {
      obstacle.x -= game.speed * (obstacle.speedMultiplier || 1) * dt;
    });
    game.obstacles = game.obstacles.filter(function (obstacle) {
      return obstacle.x + obstacle.w > -40;
    });

    game.dust.forEach(function (dot) {
      dot.x += dot.vx * dt;
      dot.life -= dt;
    });
    game.dust = game.dust.filter(function (dot) {
      return dot.life > 0;
    });

    if (!game.invincible && game.obstacles.some(collides)) {
      endGame();
    }
  }

  function updatePlayerShape() {
    var wasH = player.h;
    player.ducking = input.duckHeld && player.grounded;
    player.w = player.ducking ? player.duckW : player.standW;
    player.h = player.ducking ? player.duckH : player.standH;

    if (player.grounded || player.h !== wasH) {
      player.y = groundY - player.h;
    }
  }

  function collides(obstacle) {
    var playerBox = getPlayerHitBox();
    var obstacleBox = {
      x: obstacle.x + obstacle.hitPad,
      y: obstacle.y + obstacle.hitPad,
      w: obstacle.w - obstacle.hitPad * 2,
      h: obstacle.h - obstacle.hitPad * 2
    };

    return playerBox.x < obstacleBox.x + obstacleBox.w &&
      playerBox.x + playerBox.w > obstacleBox.x &&
      playerBox.y < obstacleBox.y + obstacleBox.h &&
      playerBox.y + playerBox.h > obstacleBox.y;
  }

  function getPlayerHitBox() {
    if (player.ducking) {
      return {
        x: player.x + 13,
        y: player.y + 18,
        w: player.w - 24,
        h: player.h - 36
      };
    }

    return {
      x: player.x + 14,
      y: player.y + 11,
      w: player.w - 26,
      h: player.h - 18
    };
  }

  function drawBackground() {
    ctx.fillStyle = "#f7fbff";
    ctx.fillRect(0, 0, W, H);

    ctx.fillStyle = "#e8f3ec";
    for (var i = 0; i < 5; i += 1) {
      var cloudX = (W - ((game.distance * 0.12 + i * 245) % (W + 160))) + 24;
      var cloudY = 44 + (i % 3) * 28;
      ctx.beginPath();
      ctx.ellipse(cloudX, cloudY, 42, 13, 0, 0, Math.PI * 2);
      ctx.ellipse(cloudX + 34, cloudY - 4, 24, 10, 0, 0, Math.PI * 2);
      ctx.ellipse(cloudX - 34, cloudY + 3, 23, 9, 0, 0, Math.PI * 2);
      ctx.fill();
    }

    drawTreadmill();
  }

  function drawTreadmill() {
    var beltTop = groundY;
    var beltHeight = 34;
    var deckTop = beltTop + beltHeight;
    var stripeOffset = (game.distance * 1.1) % 46;

    ctx.fillStyle = "#dde7e1";
    ctx.fillRect(0, deckTop + 24, W, H - deckTop - 24);

    ctx.fillStyle = "#233129";
    roundedRect(26, beltTop - 6, W - 52, beltHeight + 16, 9);
    ctx.fill();

    ctx.fillStyle = "#3f4d44";
    roundedRect(42, beltTop + 2, W - 84, beltHeight, 7);
    ctx.fill();

    ctx.save();
    ctx.beginPath();
    roundedRect(42, beltTop + 2, W - 84, beltHeight, 7);
    ctx.clip();

    ctx.fillStyle = "#526158";
    for (var x = 42 - stripeOffset; x < W - 38; x += 46) {
      ctx.beginPath();
      ctx.moveTo(x, beltTop + 2);
      ctx.lineTo(x + 16, beltTop + 2);
      ctx.lineTo(x - 8, beltTop + beltHeight + 2);
      ctx.lineTo(x - 24, beltTop + beltHeight + 2);
      ctx.closePath();
      ctx.fill();
    }

    ctx.fillStyle = "rgba(255, 255, 255, 0.16)";
    ctx.fillRect(42, beltTop + 5, W - 84, 5);
    ctx.restore();

    ctx.fillStyle = "#19231e";
    ctx.fillRect(0, beltTop - 2, W, 3);

    drawRoller(48, deckTop + 8, 22);
    drawRoller(W - 48, deckTop + 8, 22);

    ctx.fillStyle = "#8c9890";
    roundedRect(74, deckTop + 6, W - 148, 16, 5);
    ctx.fill();

    ctx.fillStyle = "#657067";
    ctx.fillRect(104, deckTop + 22, 18, 24);
    ctx.fillRect(W - 122, deckTop + 22, 18, 24);
  }

  function drawRoller(x, y, radius) {
    ctx.fillStyle = "#151f1a";
    ctx.beginPath();
    ctx.arc(x, y, radius, 0, Math.PI * 2);
    ctx.fill();

    ctx.fillStyle = "#77847b";
    ctx.beginPath();
    ctx.arc(x, y, radius - 7, 0, Math.PI * 2);
    ctx.fill();

    ctx.strokeStyle = "#26332c";
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.arc(x, y, radius - 13, 0, Math.PI * 2);
    ctx.stroke();
  }

  function roundedRect(x, y, width, height, radius) {
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.lineTo(x + width - radius, y);
    ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
    ctx.lineTo(x + width, y + height - radius);
    ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
    ctx.lineTo(x + radius, y + height);
    ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
    ctx.lineTo(x, y + radius);
    ctx.quadraticCurveTo(x, y, x + radius, y);
    ctx.closePath();
  }

  function drawPlayer() {
    var bob = player.grounded ? Math.sin(game.distance / 18) * 2 : 0;
    var img = player.ducking ? images.duck : images.runner;
    ctx.drawImage(img, player.x, player.y + bob, player.w, player.h);
  }

  function drawObstacles() {
    game.obstacles.forEach(function (obstacle) {
      ctx.drawImage(obstacle.img, obstacle.x, obstacle.y, obstacle.w, obstacle.h);
    });
  }

  function drawDust(dt) {
    ctx.fillStyle = "rgba(93, 104, 91, 0.35)";
    game.dust.forEach(function (dot) {
      ctx.globalAlpha = Math.max(0, dot.life / 0.55);
      ctx.beginPath();
      ctx.arc(dot.x, dot.y, dot.r + dt, 0, Math.PI * 2);
      ctx.fill();
    });
    ctx.globalAlpha = 1;
  }

  function drawFrame(dt) {
    drawBackground();
    drawDust(dt);
    drawObstacles();
    drawPlayer();
    updateScore();
  }

  function updateScore() {
    scoreEl.textContent = "SCORE: " + padScore(game.score || 0);
    bestEl.textContent = "HI " + padScore(bestScore);
  }

  function padScore(value) {
    return String(value).padStart(5, "0");
  }

  function loop(now) {
    if (state !== "playing") {
      return;
    }

    var dt = Math.min((now - lastTime) / 1000, 0.033);
    lastTime = now;
    update(dt);
    drawFrame(dt);

    if (state === "playing") {
      requestAnimationFrame(loop);
    }
  }

  function isCheatModalOpen() {
    return !cheatModal.classList.contains("hidden");
  }

  function isSubmitModalOpen() {
    return !submitModal.classList.contains("hidden");
  }

  function isAnyModalOpen() {
    return isCheatModalOpen() || isSubmitModalOpen();
  }

  window.addEventListener("keydown", function (event) {
    if (isAnyModalOpen()) {
      return;
    }

    if (event.code === "Space" || event.code === "ArrowUp") {
      event.preventDefault();
      playBgm();
      input.jumpHeld = true;
      jump();
    } else if (event.code === "ArrowDown") {
      event.preventDefault();
      playBgm();
      setDuck(true);
    }
  });

  window.addEventListener("keyup", function (event) {
    if (isAnyModalOpen()) {
      return;
    }

    if (event.code === "Space" || event.code === "ArrowUp") {
      event.preventDefault();
      input.jumpHeld = false;
      player.jumpHold = 0;
    } else if (event.code === "ArrowDown") {
      event.preventDefault();
      setDuck(false);
    }
  });

  canvas.addEventListener("pointerdown", function () {
    if (isAnyModalOpen()) {
      return;
    }

    playBgm();
    input.jumpHeld = true;
    jump();
  });
  canvas.addEventListener("pointerup", function () {
    if (isAnyModalOpen()) {
      return;
    }

    input.jumpHeld = false;
    player.jumpHold = 0;
  });
  canvas.addEventListener("pointercancel", function () {
    if (isAnyModalOpen()) {
      return;
    }

    input.jumpHeld = false;
    player.jumpHold = 0;
  });

  function pressJumpButton(event) {
    if (isAnyModalOpen()) {
      return;
    }

    event.preventDefault();
    playBgm();
    input.jumpHeld = true;
    jump();
  }

  function releaseJumpButton(event) {
    event.preventDefault();
    input.jumpHeld = false;
    player.jumpHold = 0;
  }

  function pressDuckButton(event) {
    if (isAnyModalOpen()) {
      return;
    }

    event.preventDefault();
    playBgm();
    setDuck(true);
  }

  function releaseDuckButton(event) {
    event.preventDefault();
    setDuck(false);
  }

  jumpButton.addEventListener("pointerdown", pressJumpButton);
  jumpButton.addEventListener("pointerup", releaseJumpButton);
  jumpButton.addEventListener("pointerleave", releaseJumpButton);
  jumpButton.addEventListener("pointercancel", releaseJumpButton);
  duckButton.addEventListener("pointerdown", pressDuckButton);
  duckButton.addEventListener("pointerup", releaseDuckButton);
  duckButton.addEventListener("pointerleave", releaseDuckButton);
  duckButton.addEventListener("pointercancel", releaseDuckButton);

  startButton.addEventListener("click", function () {
    if (isAnyModalOpen()) {
      return;
    }

    playBgm();
    startGame();
  });

  var titleClicks = 0;
  var cheatModal = document.getElementById("cheatModal");
  var cheatInput = document.getElementById("cheatInput");
  var cheatSubmit = document.getElementById("cheatSubmit");
  var cheatCancel = document.getElementById("cheatCancel");
  var titleEl = document.querySelector(".hud strong");

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

  function closeCheatModal() {
    cheatModal.classList.add("hidden");
  }

  var cheatCodes = [];

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
      document.getElementById("cheatError").classList.remove("hidden");
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
    document.getElementById("cheatError").classList.add("hidden");
  });

  // 成绩上传相关
  var uploadScoreBtn = document.getElementById("uploadScoreBtn");
  var submitModal = document.getElementById("submitModal");
  var submitNickname = document.getElementById("submitNickname");
  var submitMessage = document.getElementById("submitMessage");
  var submitError = document.getElementById("submitError");
  var submitScoreVal = document.getElementById("submitScoreVal");
  var submitConfirm = document.getElementById("submitConfirm");
  var submitCancel = document.getElementById("submitCancel");
  var ipInfo = null;

  // 页面加载时获取 IP 归属地信息
  fetch("https://game.xcnahida.cn/api/v1/ip")
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (data.code === 0 && data.data) {
        ipInfo = data;
      }
    })
    .catch(function () {
      // 获取失败则忽略，上传时 IP 和归属地为空
    });

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
    var raw = comps.join("|");
    return simpleHash(raw);
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

  function getUserDevice() {
    var ua = navigator.userAgent;
    if (/Windows/i.test(ua)) return "Windows";
    if (/Mac/i.test(ua)) return "Mac";
    if (/Android/i.test(ua)) return "Android";
    if (/iPhone|iPad|iPod/i.test(ua)) return "iOS";
    if (/Linux/i.test(ua)) return "Linux";
    return "Unknown";
  }

  function openSubmitModal() {
    if (isCheatModalOpen()) return;

    if (game.invincible) {
      showToast("作弊模式下无法上传成绩");
      return;
    }

    if (game.score === 0) {
      showToast("没有成绩数据，请先开始游戏！");
      return;
    }

    submitScoreVal.textContent = game.score;
    submitNickname.value = localStorage.getItem("seia-runner-nick") || "";
    submitMessage.value = localStorage.getItem("seia-runner-msg") || "";
    submitError.classList.add("hidden");
    submitModal.classList.remove("hidden");
    submitNickname.focus();
  }

  function closeSubmitModal() {
    submitModal.classList.add("hidden");
  }

  var toastEl = document.getElementById("toast");
  var toastTimer = 0;

  function showToast(msg) {
    clearTimeout(toastTimer);
    toastEl.textContent = msg;
    toastEl.classList.remove("hidden");
    toastTimer = setTimeout(function () {
      toastEl.classList.add("hidden");
    }, 2500);
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

  uploadScoreBtn.addEventListener("click", function () {
    if (isAnyModalOpen()) return;
    openSubmitModal();
  });

  submitCancel.addEventListener("click", function () {
    closeSubmitModal();
  });

  submitConfirm.addEventListener("click", function () {
    var nickname = submitNickname.value.trim();
    var message = submitMessage.value.trim();

    if (nickname === "") {
      submitError.textContent = "请输入昵称";
      submitError.classList.remove("hidden");
      return;
    }

    if (nickname.length > 10) {
      submitError.textContent = "昵称不能超过10个字";
      submitError.classList.remove("hidden");
      return;
    }

    if (message.length > 30) {
      submitError.textContent = "留言不能超过30个字";
      submitError.classList.remove("hidden");
      return;
    }

    submitConfirm.disabled = true;
    submitConfirm.textContent = "提交中...";
    submitError.classList.add("hidden");

    var formData = new URLSearchParams();
    formData.append("nickname", nickname);
    formData.append("message", message);
    formData.append("score", String(game.score));
    formData.append("ip_addr", ipInfo ? ipInfo.data.addr || "" : "");
    formData.append("device", getUserDevice());
    formData.append("fingerprint", getUserFingerprint());

    if (ipInfo && ipInfo.data) {
      var locParts = [];
      if (ipInfo.data.country) locParts.push(ipInfo.data.country);
      if (ipInfo.data.province) locParts.push(ipInfo.data.province);
      formData.append("location", locParts.join("·"));
    } else {
      formData.append("location", "");
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

        formData.append("score_nonce", nonceData.data.nonce);

        return fetch("api/submit_score.php", {
          method: "POST",
          credentials: "same-origin",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8"
          },
          body: formData.toString()
        });
      })
      .then(parseResponse)
      .then(function (data) {
        submitConfirm.disabled = false;
        submitConfirm.textContent = "提交";

        if (data.code === 0) {
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

  // 提交弹窗内按 Enter / Esc 处理
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

  updateScore();
  loadAssets();
}());
