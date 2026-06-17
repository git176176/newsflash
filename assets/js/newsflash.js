/* NewsFlash v1.3.0 */
document.addEventListener('DOMContentLoaded', function() {
  var cards = document.querySelectorAll('.nf-card');
  cards.forEach(function(card) {
    card.addEventListener('mouseenter', function() { this.style.transform = 'translateY(-2px)'; });
    card.addEventListener('mouseleave', function() { this.style.transform = ''; });
  });
});
