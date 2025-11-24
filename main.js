// Smooth scroll untuk tombol "Pelajari Lebih Lanjut"
document.querySelector('.btn-outline').addEventListener('click', (e) => {
  e.preventDefault();
  document.querySelector('#tentang').scrollIntoView({
    behavior: 'smooth',
    block: 'start'
  });
});

// 🌙 Navbar muncul saat discroll
window.addEventListener('scroll', () => {
  const navbar = document.querySelector('.navbar');
  if (window.scrollY > 0) {
    navbar.classList.add('scrolled');
  } else {
    navbar.classList.remove('scrolled');
  }
});

// Mencegah scroll ke bawah jika href mengarah ke file HTML lain
document.querySelectorAll('a').forEach(link => {
  link.addEventListener('click', e => {
    const href = link.getAttribute('href');
    if (href && href.endsWith('.html')) {
      e.preventDefault();
      window.location.href = href;
    }
  });
});

fetch('../php/getuser.php', {
  credentials: 'include' // 👈 ini penting!
})
  .then(res => res.json())
  .then(data => {
    console.log(data);
    const span = document.getElementById('user-name');
    if (data.name) span.textContent = data.name;
    else window.location.href = '../html/login.html';
  });

