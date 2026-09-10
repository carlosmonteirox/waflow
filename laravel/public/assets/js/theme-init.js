const storedThemeMode = localStorage.getItem('kt-theme') || 'system';
const themeMode = storedThemeMode === 'system'
    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
    : storedThemeMode;

document.documentElement.classList.remove('light', 'dark');
document.documentElement.classList.add(themeMode);
document.documentElement.dataset.ktThemeMode = themeMode;
document.documentElement.style.colorScheme = themeMode;
document.querySelector('meta[name="theme-color"]')?.setAttribute('content', themeMode === 'dark' ? '#09090b' : '#ffffff');
