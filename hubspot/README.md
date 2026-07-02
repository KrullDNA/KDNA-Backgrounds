# KDNA Animated Background — HubSpot module

A HubSpot CMS custom module that runs the KDNA **Wash** gradient with an
optional **Fluted glass** effect. It is the same WebGL engine used by the
WordPress plugin, repackaged for HubSpot CMS.

> **What "for HubSpot" means here.** HubSpot websites are built on **CMS Hub**,
> whose page building blocks are *custom modules*. Custom modules are the
> HubSpot equivalent of a WordPress plugin/widget. They are made of HTML +
> **HubL** (HubSpot's templating language, a Java/Jinja dialect) + CSS + JS.
> The visual effect itself is plain WebGL/JavaScript, so it runs unchanged —
> only the settings UI and packaging are HubSpot‑native. (The CRM record
> screens — contacts, deals, etc. — can't host a canvas background; this is
> for HubSpot‑hosted **website/landing/blog pages**.)

## Contents

```
hubspot/
  kdna-background.module/
    meta.json      # module config (label, where it can be used)
    fields.json    # the editor controls (colours, wash + fluted settings)
    module.html    # markup + HubL that emits the canvas and a JSON config
    module.css     # layout for the canvas/overlay
    module.js      # the WebGL engine + a bootstrap that starts each instance
```

## Install — HubSpot CLI (recommended)

1. Install the CLI and authenticate once:
   ```bash
   npm install -g @hubspot/cli
   hs init            # creates hubspot.config.yml and connects your account
   ```
2. From the repository root, upload the module into your Design Manager:
   ```bash
   hs upload hubspot/kdna-background.module "kdna-background.module"
   ```
   (The second argument is the destination path in Design Manager — change it
   if you keep modules in a theme folder, e.g. `my-theme/modules/kdna-background.module`.)
3. In the page editor, add the **KDNA Animated Background** module to a page.

To iterate locally with live upload:
```bash
hs watch hubspot/kdna-background.module "kdna-background.module"
```

## Install — Design Manager (no CLI)

1. In HubSpot go to **Marketing → Files and Templates → Design Tools**.
2. **File → New file → Module**, name it `kdna-background`, choose where it can
   be used (Pages / Blog), and create it.
3. Open each generated tab (`module.html`, `module.css`, `module.js`,
   and the **Edit fields**/JSON view) and paste in the contents of the matching
   file from `kdna-background.module/`. For the fields, use the `</>`
   (edit as JSON) toggle and paste `fields.json`.
4. **Publish changes** (top right).

## Editor sections

The module mirrors the WordPress plugin's layout, trimmed to just the two
effects this job needs:

1. **Gradient Colours** — 2–10 colours (drag to reorder). Each has a **% of
   mix** that sets how much of the background that colour fills (not opacity);
   values are balanced automatically, so they need not total 100.
2. **Animation Settings** — Speed, Wave Amplitude, Mesh Density, Randomness
   Seed, Darken Top Edge.
3. **Colour Shapes (Wash)** — Flow Amount, Flow Angle, Shape Definition, Colour
   Spread, Satin Sheen, Film Grain, Dominant Background Colour. (The shape style
   is fixed to Wash.)
4. **Glass Refraction (Fluted)** — Glass Effect (None / Fluted), Refraction
   Strength, Rib Count, Rib Angle, Rib Sharpness, Highlight Width/Strength,
   Shadow Width/Strength. (The glass type is fixed to Fluted; the rib controls
   appear when it's set to Fluted.)
5. **Layout** — Height (a background module needs a height to render into), an
   optional colour overlay, and optional content shown over the background.

Defaults are set so a freshly dropped module already shows a Wash gradient with
the Fluted effect on.

## Notes

- The control ranges/defaults match the WordPress plugin exactly, so a setting
  behaves identically in both.
- The effect needs WebGL; the engine falls back to a static CSS gradient where
  WebGL isn't available. It pauses when scrolled off‑screen and respects
  `prefers-reduced-motion`.
- Only **Wash** + **Fluted** are exposed. The engine still contains the other
  styles (concentric, bands, liquid, diamond, hexagon, organic); they're simply
  not surfaced as fields here.
- `module.js` is the plugin's `assets/js/gradient-engine.js` concatenated with a
  small bootstrap. To update the effect, regenerate it from the engine rather
  than editing by hand.
