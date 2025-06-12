// assets/js/main.js

$(document).ready(function () {
  // Auto-hide alerts after 5 seconds
  setTimeout(function () {
    $(".alert").fadeOut("slow");
  }, 5000);

  // Confirm delete actions
  $(".delete-confirm").click(function (e) {
    if (!confirm("Bạn có chắc chắn muốn xóa?")) {
      e.preventDefault();
    }
  });

  // Mark lesson as completed
  $("#completeBtn").click(function () {
    const button = $(this);
    const lessonId = button.data("lesson-id");

    button
      .prop("disabled", true)
      .html('<span class="loading-spinner"></span> Đang xử lý...');

    $.ajax({
      url: "/api/progress.php",
      method: "POST",
      data: {
        lesson_id: lessonId,
        action: "complete",
      },
      success: function (response) {
        if (response.success) {
          button.removeClass("btn-primary").addClass("btn-success");
          button.html('<i class="bi bi-check-circle-fill"></i> Đã hoàn thành');

          // Update progress bar if exists
          updateCourseProgress();

          // Auto next lesson after 2 seconds
          setTimeout(function () {
            const nextBtn = $("#nextLessonBtn");
            if (nextBtn.length > 0) {
              window.location.href = nextBtn.attr("href");
            }
          }, 2000);
        }
      },
      error: function () {
        button.prop("disabled", false).html("Đánh dấu hoàn thành");
        alert("Có lỗi xảy ra, vui lòng thử lại!");
      },
    });
  });

  // Search functionality
  $("#searchForm").submit(function (e) {
    e.preventDefault();
    const searchTerm = $("#searchInput").val().trim();

    if (searchTerm.length < 2) {
      alert("Vui lòng nhập ít nhất 2 ký tự để tìm kiếm");
      return;
    }

    window.location.href = "/search.php?q=" + encodeURIComponent(searchTerm);
  });

  // Live search suggestions
  let searchTimeout;
  $("#searchInput").on("input", function () {
    const query = $(this).val().trim();
    const resultsDiv = $("#searchResults");

    clearTimeout(searchTimeout);

    if (query.length < 2) {
      resultsDiv.hide();
      return;
    }

    searchTimeout = setTimeout(function () {
      $.get("/api/search.php", { q: query, limit: 5 }, function (data) {
        if (data.results && data.results.length > 0) {
          let html = '<div class="list-group">';
          data.results.forEach(function (course) {
            html += `
                            <a href="/course-detail.php?id=${course.id}" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">${course.title}</h6>
                                    <small>${course.category_name}</small>
                                </div>
                                <small class="text-muted">${course.lesson_count} bài học</small>
                            </a>
                        `;
          });
          html += "</div>";
          resultsDiv.html(html).show();
        } else {
          resultsDiv
            .html('<p class="text-muted p-3">Không tìm thấy kết quả</p>')
            .show();
        }
      });
    }, 300);
  });

  // Hide search results when clicking outside
  $(document).click(function (e) {
    if (!$(e.target).closest(".search-box").length) {
      $("#searchResults").hide();
    }
  });

  // Enrollment button
  $("#enrollBtn").click(function () {
    const button = $(this);
    const courseId = button.data("course-id");

    button
      .prop("disabled", true)
      .html('<span class="loading-spinner"></span> Đang xử lý...');

    $.post("/api/enroll.php", { course_id: courseId }, function (response) {
      if (response.success) {
        button.removeClass("btn-primary").addClass("btn-success");
        button.html('<i class="bi bi-check-circle"></i> Đã đăng ký');

        // Redirect to first lesson
        setTimeout(function () {
          window.location.href = response.redirect_url;
        }, 1000);
      } else {
        button.prop("disabled", false).html("Đăng ký học");
        alert(response.message || "Có lỗi xảy ra!");
      }
    }).fail(function () {
      button.prop("disabled", false).html("Đăng ký học");
      alert("Có lỗi xảy ra, vui lòng thử lại!");
    });
  });

  // Update course progress
  function updateCourseProgress() {
    const courseId = $("#courseId").val();
    if (!courseId) return;

    $.get("/api/progress.php", { course_id: courseId }, function (data) {
      if (data.progress !== undefined) {
        $(".progress-bar").css("width", data.progress + "%");
        $(".progress-text").text(data.progress + "%");
        $(".completed-lessons").text(data.completed + "/" + data.total);
      }
    });
  }

  // Video player tracking
  if ($("#youtube-player").length > 0) {
    let player;
    let videoId = $("#youtube-player").data("video-id");
    let lessonId = $("#youtube-player").data("lesson-id");
    let lastUpdateTime = 0;

    // Load YouTube API
    window.onYouTubeIframeAPIReady = function () {
      player = new YT.Player("youtube-player", {
        videoId: videoId,
        playerVars: {
          autoplay: 0,
          controls: 1,
          rel: 0,
          modestbranding: 1,
        },
        events: {
          onStateChange: onPlayerStateChange,
        },
      });
    };

    function onPlayerStateChange(event) {
      if (event.data == YT.PlayerState.PLAYING) {
        trackProgress();
      } else if (event.data == YT.PlayerState.ENDED) {
        $("#completeBtn").click();
      }
    }

    function trackProgress() {
      setInterval(function () {
        if (player && player.getCurrentTime) {
          let currentTime = Math.floor(player.getCurrentTime());

          // Update every 10 seconds
          if (currentTime - lastUpdateTime >= 10) {
            $.post("/api/progress.php", {
              lesson_id: lessonId,
              action: "update_time",
              time: currentTime,
            });
            lastUpdateTime = currentTime;
          }
        }
      }, 1000);
    }

    // Load YouTube API script
    const tag = document.createElement("script");
    tag.src = "https://www.youtube.com/iframe_api";
    const firstScriptTag = document.getElementsByTagName("script")[0];
    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
  }

  // Smooth scroll to sections
  $('a[href^="#"]').on("click", function (e) {
    e.preventDefault();
    const target = $(this.getAttribute("href"));
    if (target.length) {
      $("html, body").animate(
        {
          scrollTop: target.offset().top - 80,
        },
        800
      );
    }
  });

  // Form validation
  $("form.needs-validation").on("submit", function (e) {
    if (!this.checkValidity()) {
      e.preventDefault();
      e.stopPropagation();
    }
    $(this).addClass("was-validated");
  });

  // Tooltip initialization
  $('[data-bs-toggle="tooltip"]').tooltip();

  // Copy to clipboard
  $(".copy-btn").click(function () {
    const text = $(this).data("copy");
    navigator.clipboard.writeText(text).then(function () {
      alert("Đã copy!");
    });
  });
});
