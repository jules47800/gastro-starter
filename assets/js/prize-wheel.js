jQuery(document).ready(function ($) {
  // --- State & Config ---
  const data = window.prizeWheelData;
  let userEmail = "";
  let theWheel;
  let spinClicked = false;

  // --- Init ---
  initWheel();
  checkPlayedCookie();

  // --- Event Listeners ---

  // Step 1: Email Submit
  $("#email-form").on("submit", function (e) {
    e.preventDefault();
    const email = $("#player-email").val();

    if (!validateEmail(email)) {
      showError("Veuillez entrer une adresse email valide.");
      return;
    }

    userEmail = email;
    goToStep("review");
  });

  // Step 2: Review Click
  $("#review-btn").on("click", function (e) {
    // Open Google Review in new tab
    const url = data.google_review_url || "#";
    if (url !== "#") {
      window.open(url, "_blank");
    }

    // Show "Checking" message and wait a bit to simulate verification/return
    $("#review-check-msg").show();

    setTimeout(function () {
      goToStep("wheel");
      // Resize wheel after it becomes visible to ensure correct rendering
      theWheel.draw();
    }, 2000); // 2 seconds delay
  });

  // Step 3: Spin Click
  $("#spin-btn").on("click", function () {
    if (spinClicked) return;

    // Disable button
    $(this).prop("disabled", true).text("Bonne chance !");
    spinClicked = true;

    // Call AJAX to get the result
    $.ajax({
      url: data.ajaxurl,
      type: "POST",
      data: {
        action: "gastro_starter_spin_wheel",
        nonce: data.nonce,
        email: userEmail,
      },
      success: function (response) {
        if (response.success) {
          // Start the spin animation
          const segmentIndex = response.data.segment_index; // 0-based

          // Calculate stop angle
          // Winwheel uses 1-based index for stopAnimation, but we can calculate angle.
          // Actually Winwheel has stopAtAnimation(segmentNumber)
          // segmentNumber is 1-based.

          const stopAt = theWheel.getRandomForSegment(
            parseInt(segmentIndex) + 1
          );

          theWheel.animation.stopAngle = stopAt;
          theWheel.startAnimation();

          // Store result for display after spin
          theWheel.winMessage = response.data.message;
          theWheel.prizeLabel = response.data.label;
          theWheel.isWin = response.data.is_win;

          // Reset finished flag
          hasFinished = false;

          // SAFETY TIMEOUT: Force result after 6 seconds if callback fails
          setTimeout(function() {
            if (!hasFinished) {
                console.log("Safety timeout triggered: Forcing result screen.");
                window.alertPrize();
            }
          }, 6000); // 5s animation + 1s buffer

          // Set Cookie/LocalStorage to prevent replay
          setPlayedCookie();
        } else {
          showError(response.data.message);
          $("#spin-btn").prop("disabled", false).text("LANCER LA ROUE !");
          spinClicked = false;
        }
      },
      error: function () {
        showError("Une erreur est survenue. Veuillez réessayer.");
        $("#spin-btn").prop("disabled", false).text("LANCER LA ROUE !");
        spinClicked = false;
      },
    });
  });

  // --- Functions ---

  // Flag to prevent double execution
  let hasFinished = false;

  // Make global for Winwheel reliability
  window.alertPrize = function(indicatedSegment) {
    if (hasFinished) return;
    hasFinished = true;

    // Animation finished
    // Ensure isWin is treated as boolean
    const isWin = (theWheel.isWin == 1 || theWheel.isWin === true || theWheel.isWin === "1");

    if (isWin) {
        $("#result-icon").html("🎁");
        $("#result-title").text("Félicitations !");
        $("#result-title").css("color", "#27ae60");
        
        // Display Prize Name prominently
        const prizeName = theWheel.prizeLabel || "Un cadeau surprise";
        $("#result-message").html("<h3 style='font-size: 1.8rem; color: #d35400; margin: 10px 0;'>" + prizeName + "</h3>" + (theWheel.winMessage || ""));
        
        $("#result-extra-info").text("Un email contenant votre gain vous a été envoyé. Présentez-le lors de votre prochaine visite !");
        startConfetti();
    } else {
        $("#result-icon").html("😢");
        $("#result-title").text("Dommage...");
        $("#result-title").css("color", "#c0392b");
        $("#result-message").html(theWheel.winMessage || "");
        $("#result-extra-info").text("Merci d'avoir participé ! Retentez votre chance une prochaine fois.");
    }

    goToStep("result");
  };

  function initWheel() {
    // Parse prizes from PHP data
    // We need to convert PHP array to Winwheel segments
    // PHP: [{label, color, ...}, ...]

    const segments = [];
    if (data.prizes && data.prizes.length > 0) {
      data.prizes.forEach((p) => {
        segments.push({
          fillStyle: p.color,
          text: p.label,
          textFillStyle: "#ffffff",
          strokeStyle: "#ffffff",
        });
      });
    } else {
      // Fallback
      segments.push({ fillStyle: "#eae56f", text: "Prize 1" });
      segments.push({ fillStyle: "#89f26e", text: "Prize 2" });
      segments.push({ fillStyle: "#7de6ef", text: "Prize 3" });
      segments.push({ fillStyle: "#e7706f", text: "Prize 4" });
    }

    theWheel = new Winwheel({
      canvasId: "canvas",
      numSegments: segments.length,
      segments: segments,
      outerRadius: 170, // Set outer radius so wheel fits inside the background.
      innerRadius: 40, // Make it a donut wheel
      textFontSize: 16,
      textOrientation: "horizontal", // or vertical
      textAlignment: "center",
      animation: {
        type: "spinToStop",
        duration: 5,
        spins: 8,
        callbackFinished: "alertPrize", // Use STRING reference for global function
        callbackAfter: drawPointer,
      },
      responsive: true, // Enable responsive resizing
    });
  }

  // Removed local alertPrize function since it's now global

  function drawPointer() {
    // Optional: Custom pointer drawing on canvas if needed,
    // but we used a CSS pointer so this might not be needed unless we want to clear/redraw.
    // Winwheel clears canvas on draw, so we rely on CSS pointer overlay.
  }

  function goToStep(stepId) {
    $(".wheel-step").removeClass("active").hide(); // Force hide
    $("#step-" + stepId).addClass("active").show(); // Force show
  }

  function showError(msg) {
    $("#wheel-message").text(msg).addClass("error").show();
    setTimeout(() => {
      $("#wheel-message").fadeOut();
    }, 5000);
  }

  function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
  }

  function setPlayedCookie() {
    localStorage.setItem("gastro_starter_wheel_played", "true");
    // Also set a cookie for server-side if needed, but JS check is enough for basic UX
    document.cookie = "gastro_starter_wheel_played=true; max-age=31536000; path=/";
  }

  function checkPlayedCookie() {
    if (
      localStorage.getItem("gastro_starter_wheel_played") === "true" ||
      document.cookie.indexOf("gastro_starter_wheel_played=true") !== -1
    ) {
      // User has already played
      $(".wheel-container").html(
        '<div class="wheel-step active"><h2>Déjà joué !</h2><p>Vous avez déjà tenté votre chance. Revenez une prochaine fois !</p></div>'
      );
    }
  }

  // Confetti Effect (Real version using canvas-confetti)
  function startConfetti() {
    // Check if confetti library is loaded
    if (typeof confetti === 'function') {
        var duration = 3 * 1000;
        var animationEnd = Date.now() + duration;
        var defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 9999 };

        var random = function(min, max) {
          return Math.random() * (max - min) + min;
        }

        var interval = setInterval(function() {
          var timeLeft = animationEnd - Date.now();

          if (timeLeft <= 0) {
            return clearInterval(interval);
          }

          var particleCount = 50 * (timeLeft / duration);
          // since particles fall down, start a bit higher than random
          confetti(Object.assign({}, defaults, { particleCount, origin: { x: random(0.1, 0.3), y: Math.random() - 0.2 } }));
          confetti(Object.assign({}, defaults, { particleCount, origin: { x: random(0.7, 0.9), y: Math.random() - 0.2 } }));
        }, 250);
    } else {
        // Fallback
        $("body").css("background-color", "#fff9c4");
    }
  }
});
