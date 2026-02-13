import { ref, onMounted } from 'vue';

type Theme = 'light' | 'dark';

const currentTheme = ref<Theme>('light');

export function useTheme() {
    const setTheme = (theme: Theme) => {
        currentTheme.value = theme;
        const html = document.documentElement;

        if (theme === 'dark') {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }

        localStorage.setItem('theme', theme);
    };

    const toggleTheme = () => {
        setTheme(currentTheme.value === 'light' ? 'dark' : 'light');
    };

    const initTheme = () => {
        // Check localStorage first
        const savedTheme = localStorage.getItem('theme') as Theme | null;

        if (savedTheme) {
            setTheme(savedTheme);
        } else {
            // Check system preference
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            setTheme(prefersDark ? 'dark' : 'light');
        }
    };

    onMounted(() => {
        initTheme();
    });

    // Listen for system preference changes
    onMounted(() => {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addEventListener('change', (e) => {
            if (!localStorage.getItem('theme')) {
                setTheme(e.matches ? 'dark' : 'light');
            }
        });
    });

    return {
        currentTheme,
        setTheme,
        toggleTheme,
        initTheme,
    };
}
