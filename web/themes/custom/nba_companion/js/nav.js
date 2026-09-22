(function () {
  function setupBackButton() {
    const backLink = document.querySelector('[data-nba-back]');
    if (!backLink) return;

    backLink.addEventListener('click', function (event) {
      let previousPageIsOnThisSite = false;

      try {
        previousPageIsOnThisSite =
          document.referrer !== '' &&
          new URL(document.referrer).origin === window.location.origin;
      } catch (error) {
        // Keep the link's Home destination.
      }

      if (previousPageIsOnThisSite && window.history.length > 1) {
        event.preventDefault();
        window.history.back();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupBackButton, {
      once: true
    });
  } else {
    setupBackButton();
  }
})();