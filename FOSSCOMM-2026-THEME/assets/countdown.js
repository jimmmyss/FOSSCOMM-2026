const el = document.querySelector('[data-fc-countdown-clock]')
if (el && window.FC_DATA && window.FC_DATA.eventStart) {
  const target = new Date(window.FC_DATA.eventStart).getTime()
  if (!Number.isNaN(target)) {
    const tick = () => {
      const diff = Math.max(0, target - Date.now())
      const days = Math.floor(diff / 86400000)
      const hours = Math.floor((diff / 3600000) % 24)
      const mins = Math.floor((diff / 60000) % 60)
      const secs = Math.floor((diff / 1000) % 60)
      el.textContent = `${days}D ${hours}H ${mins}M ${secs}S`
    }
    tick()
    setInterval(tick, 1000)
  }
}
