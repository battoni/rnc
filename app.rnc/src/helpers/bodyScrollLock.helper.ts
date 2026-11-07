export default function toggleBodyScrollLock(): void {
  const hasClass = document.body.classList.contains('overflow-hidden');

  document.body.classList.toggle('overflow-hidden', !hasClass);
}
