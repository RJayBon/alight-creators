/* ============================================================
   Home — Tutorial carousels
   • Prev/next buttons scroll one card at a time (desktop)
   • Touch swipe works natively via CSS overflow-x + scroll-snap
   • Arrows fade out when there's nothing to scroll in that direction
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-carousel]').forEach((carousel) => {
    const track   = carousel.querySelector('[data-carousel-track]');
    const prevBtn = carousel.querySelector('[data-carousel-prev]');
    const nextBtn = carousel.querySelector('[data-carousel-next]');
    if (!track || !prevBtn || !nextBtn) return;

    /* ---- Distance of one card step, gap included ---- */
    function getStep() {
      const cards = track.querySelectorAll('.tutorial-card');
      if (cards.length < 2) return track.clientWidth;
      /* offsetLeft delta between adjacent cards includes the flex gap */
      return cards[1].offsetLeft - cards[0].offsetLeft;
    }

    function scrollBy(direction) {
      track.scrollBy({ left: direction * getStep(), behavior: 'smooth' });
    }

    prevBtn.addEventListener('click', () => scrollBy(-1));
    nextBtn.addEventListener('click', () => scrollBy(1));

    /* ---- Keep the arrows in sync with the scroll position ---- */
    function updateButtons() {
      const maxScroll = track.scrollWidth - track.clientWidth;

      /* Tolerance of 4px absorbs sub-pixel rounding from scroll-snap */
      const atStart = track.scrollLeft <= 4;
      const atEnd   = track.scrollLeft >= maxScroll - 4;

      /* Nothing to scroll at all → hide BOTH arrows */
      const noScroll = maxScroll <= 4;

      /* Fade arrows in/out based on whether there's content in that direction */
      prevBtn.classList.toggle('is-hidden', noScroll || atStart);
      nextBtn.classList.toggle('is-hidden', noScroll || atEnd);

      /* Turn off the right-edge fade when we've reached the last card */
      track.classList.toggle('at-end', atEnd || noScroll);
    }

    track.addEventListener('scroll', updateButtons, { passive: true });
    window.addEventListener('resize', updateButtons);

    /* Keyboard support when a button is focused */
    carousel.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowLeft')  { e.preventDefault(); scrollBy(-1); }
      if (e.key === 'ArrowRight') { e.preventDefault(); scrollBy(1);  }
    });

    /* Set the initial state after the browser has laid out the cards */
    requestAnimationFrame(updateButtons);
  });
});