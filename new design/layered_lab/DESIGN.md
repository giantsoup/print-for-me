# Design System Specification: The Luminous Layer

This design system is a high-end framework crafted for an intimate, invite-only 3D printing community. It rejects the "heavy SaaS" aesthetic in favor of an editorial, lightweight experience that feels like a private workshop. By leveraging deep tonal layering and vibrant neon accents, we create a space that feels both technologically advanced and personally welcoming.

---

## 1. Creative North Star: "The Digital Workbench"
The North Star for this system is the **Digital Workbench**. Imagine a darkened studio where the only light comes from the glowing precision of a 3D printer. 

To achieve this, we break the "template" look through:
*   **Intentional Asymmetry:** Avoid perfectly centered, rigid grids. Use the spacing scale to create "breathing rooms" that allow 3D model previews to dominate the visual field.
*   **Chromatic Depth:** Instead of flat grey, we use a sophisticated stack of charcoal and black surfaces that feel "infinite."
*   **The Printing Glow:** Accent colors (Primary Green and Secondary Blue) are treated as light sources, not just fills.

---

## 2. Color & Atmospheric Tones
We move beyond flat UI by treating color as light and surfaces as physical materials.

### The "No-Line" Rule
**Strict Mandate:** Designers are prohibited from using 1px solid borders to section content. Boundaries must be defined solely through background color shifts. 
*   Place a `surface-container-low` card on a `surface` background.
*   The transition from `#0e0e0e` to `#131313` provides all the separation a sophisticated eye needs.

### Surface Hierarchy & Nesting
Treat the UI as a series of nested, translucent trays.
*   **Base:** `surface` (#0e0e0e)
*   **Sectioning:** `surface-container` (#1a1a1a)
*   **Interactive Elements:** `surface-container-high` (#20201f)
*   **Floating Modals:** `surface-bright` (#2c2c2c) with 80% opacity and a `20px` backdrop-blur.

### The Glass & Gradient Rule
For main CTAs or "Print Status" indicators, use a subtle linear gradient: 
`linear-gradient(135deg, var(--primary) 0%, var(--primary-container) 100%)`. This provides a "liquid filament" look that flat hex codes cannot replicate.

---

## 3. Typography: Editorial Precision
The system uses a pairing of **Space Grotesk** for technological flair and **Manrope** for human-centric readability.

*   **Display & Headlines (Space Grotesk):** These are our "statement" pieces. Use `display-lg` (3.5rem) with tight letter-spacing (-0.02em) for welcome screens. The geometric nature of Space Grotesk mirrors the precision of a 3D print head.
*   **Body & Titles (Manrope):** Chosen for its high x-height and legibility on mobile. Use `body-md` (0.875rem) for the bulk of the "Print Settings" data to keep the interface feeling lightweight and non-intimidating.
*   **Hierarchy Tip:** Never use "Bold" for body text. Use `medium` weight and contrast it with `on-surface-variant` (grey) for secondary info to maintain a high-end, low-friction feel.

---

## 4. Elevation & Depth: Tonal Layering
Traditional drop shadows are too "software-like." This system uses **Ambient Radiance**.

*   **The Layering Principle:** Depth is achieved by stacking. A `surface-container-lowest` card (#000000) sitting on a `surface-container-low` (#131313) creates a "recessed" effect, perfect for input areas.
*   **Ambient Shadows:** If an element must float (like a floating action button), use a shadow color derived from the accent: `rgba(161, 255, 194, 0.08)` with a `32px` blur. It should look like a soft glow, not a shadow.
*   **Ghost Border Fallback:** If a container sits on a background of the same color, use `outline-variant` (#484847) at **15% opacity**. It should be felt, not seen.

---

## 5. Signature Components

### The "Filament" Button (Primary)
*   **Background:** Gradient of `primary` to `primary-container`.
*   **Text:** `on-primary` (#00643a), uppercase, `label-md` weight.
*   **Radius:** `full` (9999px) to contrast against the geometric logo.
*   **Interaction:** On hover, increase the `surface-tint` glow effect.

### Glass Cards (3D Model Previews)
*   **Background:** `surface-container` at 70% opacity.
*   **Blur:** `12px` backdrop-filter.
*   **Constraint:** No borders. Use `xl` (1.5rem) roundedness for a friendly, modern feel.
*   **Spacing:** Use `spacing-6` (1.5rem) internal padding to ensure the model "breathes."

### Progress Indicators (The Print Path)
*   Instead of a standard bar, use a `2px` path using `secondary` (#6e9bff). 
*   Use a "glow head" (a small `primary` circle with a `10px` blur) that travels along the path to represent the 3D print head’s current position.

### Input Fields
*   **Style:** Minimalist. No box. Only a `surface-container-highest` background with an `8px` bottom radius.
*   **Focus State:** The bottom edge glows with a `primary` color transition.

---

## 6. Do’s and Don’ts

### Do:
*   **Do** use `spacing-16` or `spacing-20` for top-level section margins. White space is a luxury signal.
*   **Do** use `tertiary` (#77dfff) for purely informational "tech" stats (e.g., nozzle temperature, file size).
*   **Do** ensure all interactive touch targets on mobile are at least `48px` tall, even if the visual element is smaller.

### Don't:
*   **Don't** use pure white (#ffffff) for large blocks of text. Use `on-surface-variant` for a softer, premium reading experience.
*   **Don't** use divider lines. If you feel the need for a line, increase the `spacing` scale to the next tier instead.
*   **Don't** use standard "Error Red" for non-critical issues. Use `error_dim` (#d7383b) to keep the atmosphere calm and friendly.

---

## 7. Logo Guidance
The logo should be a **Geometric Monolith**. 
*   **Form:** Three stacked isometric rhombs forming a partial cube.
*   **Color:** The top layer should use `primary`, the middle `primary_dim`, and the base `primary_fixed_variant`.
*   **Favicon:** Use only the top-most rhomb to ensure clarity at 16x16px.