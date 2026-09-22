document.addEventListener("click", (event) => {
  if (!(event.target instanceof Element)) {
    return;
  }

  const backLink = event.target.closest("[data-nba-back]");

  if (!backLink) {
    return;
  }

  if (window.history.length > 1) {
    event.preventDefault();
    window.history.back();
  }

  // With no previous history entry, the link's href opens Home.
});