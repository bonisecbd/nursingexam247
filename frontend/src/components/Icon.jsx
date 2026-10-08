/** Inline stroke icon set used across the app (no icon dependency). */
export default function Icon({ name, size = 20, className = '' }) {
  const common = {
    width: size,
    height: size,
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.8,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
    'aria-hidden': true,
    focusable: false,
    className: className || undefined,
  }

  const paths = {
    arrow: (
      <>
        <path d="M5 12h14" />
        <path d="m12 5 7 7-7 7" />
      </>
    ),
    book: (
      <>
        <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v17H6.5A2.5 2.5 0 0 0 4 22z" />
        <path d="M4 5.5v14A2.5 2.5 0 0 1 6.5 17H20" />
        <path d="M8 7h8M8 11h7" />
      </>
    ),
    check: <path d="m5 12 4 4L19 6" />,
    clock: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M12 7v5l3 2" />
      </>
    ),
    shield: (
      <>
        <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11" />
        <path d="m9 12 2 2 4-4" />
      </>
    ),
    star: <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z" />,
    users: (
      <>
        <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
        <circle cx="10" cy="7" r="4" />
        <path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
      </>
    ),
    logout: (
      <>
        <path d="M10 17l5-5-5-5" />
        <path d="M15 12H3" />
        <path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6" />
      </>
    ),
    spark: (
      <>
        <path d="m12 3 1.9 5.8L20 11l-6.1 2.2L12 19l-1.9-5.8L4 11l6.1-2.2z" />
        <path d="m19 14 1.1 2.9L23 18l-2.9 1.1L19 22l-1.1-2.9L15 18l2.9-1.1z" />
      </>
    ),
    eye: (
      <>
        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7" />
        <circle cx="12" cy="12" r="3" />
      </>
    ),
    close: (
      <>
        <path d="m18 6-12 12M6 6l12 12" />
      </>
    ),
    menu: <path d="M4 6h16M4 12h16M4 18h16" />,
    heart: <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8" />,
    dashboard: (
      <>
        <rect x="3" y="3" width="8" height="8" rx="1.5" />
        <rect x="13" y="3" width="8" height="5" rx="1.5" />
        <rect x="13" y="10" width="8" height="11" rx="1.5" />
        <rect x="3" y="13" width="8" height="8" rx="1.5" />
      </>
    ),
    info: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M12 11v5M12 8h.01" />
      </>
    ),
    chart: (
      <>
        <path d="M3 3v18h18" />
        <path d="m19 9-5 5-4-4-5 5" />
      </>
    ),
    question: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M9.5 9a2.5 2.5 0 1 1 4.3 1.7c-1.2 1.1-1.8 1.3-1.8 3" />
        <path d="M12 17h.01" />
      </>
    ),
    search: (
      <>
        <circle cx="10.8" cy="10.8" r="6.8" />
        <path d="m16 16 5 5" />
      </>
    ),
    trophy: (
      <>
        <path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0z" />
        <path d="M7 6H4v2a4 4 0 0 0 4 4M17 6h3v2a4 4 0 0 1-4 4" />
      </>
    ),
    tag: (
      <>
        <path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8" />
        <circle cx="7.5" cy="7.5" r="1" />
      </>
    ),
    wallet: (
      <>
        <rect x="3" y="5" width="18" height="15" rx="2" />
        <path d="M3 8h18M16 14h2" />
        <path d="M6 5V3h12" />
      </>
    ),
    payment: (
      <>
        <rect x="2" y="5" width="20" height="14" rx="2" />
        <path d="M2 10h20M6 15h3" />
      </>
    ),
    bell: (
      <>
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
      </>
    ),
    settings: (
      <>
        <circle cx="12" cy="12" r="3" />
        <path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.7-.2.1-1.2.7-.3 1.8h-2.8l-.3-1.8-1.4-.8-1.7.7-1.4-2.4 1.4-1.1v-1.6l-1.4-1.1 1.4-2.4 1.7.7 1.4-.8.3-1.8h2.8l.3 1.8 1.4.8 1.7-.7 1.4 2.4-1.4 1.1z" />
      </>
    ),
    flag: <path d="M5 21V4h9l-1 3h6l-2 5 2 5h-9l-1-3H5" />,
    lock: (
      <>
        <rect x="4" y="10" width="16" height="11" rx="2" />
        <path d="M8 10V7a4 4 0 0 1 8 0v3" />
      </>
    ),
    unlock: (
      <>
        <rect x="4" y="10" width="16" height="11" rx="2" />
        <path d="M8 10V7a4 4 0 0 1 7.5-2" />
      </>
    ),
    play: <path d="M8 5.5v13l11-6.5z" />,
    inbox: (
      <>
        <path d="M3 13h5l1.5 3h5L16 13h5" />
        <path d="M5 5h14l2 8v6H3v-6z" />
      </>
    ),
    refresh: (
      <>
        <path d="M20 11a8 8 0 1 0-1.7 5.3" />
        <path d="M20 5v6h-6" />
      </>
    ),
    camera: (
      <>
        <path d="M4 8h3l1.5-2h7L17 8h3v11H4z" />
        <circle cx="12" cy="13" r="3.5" />
      </>
    ),
    chevronLeft: <path d="m14 6-6 6 6 6" />,
    chevronRight: <path d="m10 6 6 6-6 6" />,
    target: (
      <>
        <circle cx="12" cy="12" r="9" />
        <circle cx="12" cy="12" r="5" />
        <circle cx="12" cy="12" r="1.4" />
      </>
    ),
    list: (
      <>
        <path d="M8 6h13M8 12h13M8 18h13" />
        <path d="M3.5 6h.01M3.5 12h.01M3.5 18h.01" />
      </>
    ),
    award: (
      <>
        <circle cx="12" cy="9" r="5" />
        <path d="m8.5 13.5-1.5 7 5-2.5 5 2.5-1.5-7" />
      </>
    ),
    alert: (
      <>
        <path d="M12 4 2.5 20h19z" />
        <path d="M12 10v4M12 17h.01" />
      </>
    ),
    graduation: (
      <>
        <path d="M2 8.5 12 4l10 4.5L12 13z" />
        <path d="M6 10.5V16c0 1.7 2.7 3 6 3s6-1.3 6-3v-5.5" />
      </>
    ),
    megaphone: (
      <>
        <path d="m3 11 18-5v12L3 13z" />
        <path d="M11 15 13 22H8l-2-8" />
        <path d="M21 10h1" />
      </>
    ),
  }

  return <svg {...common}>{paths[name] || paths.spark}</svg>
}
