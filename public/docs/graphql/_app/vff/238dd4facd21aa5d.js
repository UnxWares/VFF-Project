// Detect the user's preference for dark mode
            function setTheme() {
                const saved = document.cookie.match(/(?:^|; )theme=(dark|light)(?:;|$)/); const prefersDarkScheme = saved ? saved[1] === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches
                if (prefersDarkScheme) {
                    document.documentElement.setAttribute('theme', 'g100')
                } else {
                    document.documentElement.setAttribute('theme', 'white')
                }
            }

            setTheme()

            window
                .matchMedia('(prefers-color-scheme: dark)')
                .addEventListener('change', setTheme)
