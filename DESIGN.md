---
name: Emerald Nocturne
colors:
  surface: '#131313'
  surface-dim: '#131313'
  surface-bright: '#3a3939'
  surface-container-lowest: '#0e0e0e'
  surface-container-low: '#1c1b1b'
  surface-container: '#201f1f'
  surface-container-high: '#2a2a2a'
  surface-container-highest: '#353534'
  on-surface: '#e5e2e1'
  on-surface-variant: '#bbcabf'
  inverse-surface: '#e5e2e1'
  inverse-on-surface: '#313030'
  outline: '#86948a'
  outline-variant: '#3c4a42'
  surface-tint: '#4edea3'
  primary: '#4edea3'
  on-primary: '#003824'
  primary-container: '#10b981'
  on-primary-container: '#00422b'
  inverse-primary: '#006c49'
  secondary: '#bdc7d9'
  on-secondary: '#27313f'
  secondary-container: '#404a59'
  on-secondary-container: '#afb9cb'
  tertiary: '#ffb3af'
  on-tertiary: '#650911'
  tertiary-container: '#fc7c78'
  on-tertiary-container: '#711419'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#6ffbbe'
  primary-fixed-dim: '#4edea3'
  on-primary-fixed: '#002113'
  on-primary-fixed-variant: '#005236'
  secondary-fixed: '#d9e3f6'
  secondary-fixed-dim: '#bdc7d9'
  on-secondary-fixed: '#121c2a'
  on-secondary-fixed-variant: '#3d4756'
  tertiary-fixed: '#ffdad7'
  tertiary-fixed-dim: '#ffb3af'
  on-tertiary-fixed: '#410005'
  on-tertiary-fixed-variant: '#842225'
  background: '#131313'
  on-background: '#e5e2e1'
  surface-variant: '#353534'
typography:
  headline-lg:
    fontFamily: Geist
    fontSize: 48px
    fontWeight: '600'
    lineHeight: '1.1'
    letterSpacing: -0.02em
  headline-lg-mobile:
    fontFamily: Geist
    fontSize: 32px
    fontWeight: '600'
    lineHeight: '1.2'
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Geist
    fontSize: 24px
    fontWeight: '500'
    lineHeight: '1.3'
  body-md:
    fontFamily: Geist
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.6'
  label-sm:
    fontFamily: JetBrains Mono
    fontSize: 13px
    fontWeight: '500'
    lineHeight: '1.2'
    letterSpacing: 0.05em
  code-snippet:
    fontFamily: JetBrains Mono
    fontSize: 14px
    fontWeight: '400'
    lineHeight: '1.5'
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  base: 8px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 64px
  max-width: 1280px
---

## Brand & Style
This design system centers on a high-end, developer-centric aesthetic that balances extreme minimalism with vibrant, functional accents. The brand personality is precise, technical, and sophisticated, targeting a professional audience that values efficiency and visual clarity.

The design style is **Minimalism with Glassmorphism influences**. It utilizes a deep, dark charcoal canvas to reduce eye strain, while employing "Emerald Green" as a surgical accent to guide focus and signify action. High-quality typography and generous whitespace create a sense of calm and technical mastery.

## Colors
The palette is dominated by a dark charcoal base (`#0a0a0a`), providing a void-like backdrop that makes the content feel as though it is floating. 

- **Primary Emerald (`#10b981`):** Reserved for high-priority actions, progress indicators, and critical data points.
- **Secondary Charcoal (`#1f2937`):** Used for surface-level cards and containers to provide subtle contrast against the background.
- **Neutral Accents:** Greys are kept strictly neutral to avoid color temperature shifts, ensuring the emerald remains the sole chromatic focus.

## Typography
The typography relies on **Geist** for its technical precision and **JetBrains Mono** for secondary data and labeling. This pairing evokes a "developer-tool" sophistication. 

Large headlines should use tight tracking and leading to maintain a dense, modern look. Labels and captions should always be in the monospaced font, often in uppercase, to lean into the technical aesthetic of the design system.

## Layout & Spacing
The layout follows a **Fluid Grid** model with a maximum container width for desktop viewing. We utilize an 8px rhythmic scale for all margins and padding.

- **Desktop:** 12-column grid with 24px gutters.
- **Tablet:** 8-column grid with 20px gutters.
- **Mobile:** 4-column grid with 16px gutters and 16px side margins.

Content should be grouped logically into "sections" separated by 80px to 120px of vertical whitespace to maintain the minimalist breathability of the design system.

## Elevation & Depth
Depth is created through **Tonal Layers** and **Subtle Blurs** rather than traditional heavy shadows.

- **Level 0 (Background):** Pure charcoal (`#0a0a0a`).
- **Level 1 (Surface):** Subtle elevation using `#161616` with a 1px low-contrast stroke (`#ffffff10`).
- **Level 2 (Overlays):** Semi-transparent surfaces using a backdrop blur (20px) and a slightly lighter fill.

Shadows, when used, are "Ambient Glows" where the shadow color is a dark tint of the primary Emerald Green (`#10b981`) at very low opacity (5-10%) to suggest a subtle neon luminescence.

## Shapes
The shape language is **Soft (0.25rem)**. This provides a slight hint of approachability while maintaining the overall sharp, professional geometric feel of the design system. 

Large containers and cards utilize the `rounded-lg` (0.5rem) token, while buttons and input fields stick to the base `rounded` (0.25rem) to ensure they feel like structural parts of the interface.

## Components
- **Buttons:** Primary buttons are solid Emerald Green with black text for maximum legibility. Secondary buttons use a ghost style with a 1px emerald border and emerald text.
- **Chips:** Monospaced labels inside small, pill-shaped containers with a subtle `emerald/10` background tint.
- **Inputs:** Dark backgrounds (`#0a0a0a`) with a 1px border that glows emerald upon focus.
- **Cards:** No shadows; defined by a subtle `1px` border in `#ffffff15`.
- **Lists:** Separated by thin, low-opacity lines. Interactive list items should have a hover state that slightly increases the background luminosity.
- **Status Indicators:** Always use the Emerald Green for "success" or "online" states, ensuring it remains the hero color across the UI.