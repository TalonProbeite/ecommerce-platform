---
trigger: always_on
description: "Rentify frontend architecture, UI, accessibility, responsive behavior, and maintainability rules."
---
# Rentify Frontend Rules

- Use the existing React/Vite/TypeScript/Tailwind/shadcn stack exactly as established in the project.
- Reuse components before creating variants. If a suitable shadcn/ui component exists, use or adapt it rather than rebuilding it.
- Preserve one coherent visual language across the product.
- Favor semantic HTML, visible focus states, keyboard access, accessible labels, and actionable error messages.
- Keep mobile layouts intentional; do not treat mobile as a compressed desktop.
- Avoid gratuitous animations, glassmorphism, excessive gradients, dense tables for consumer marketplace views, and generic AI-looking layouts.
- Use strong hierarchy and whitespace; marketplace/catalog surfaces should feel consumer-grade.
- Keep route definitions centralized, global state minimal, API calls behind service functions, and mock data out of page components.
- Do not create duplicate components because two pages happen to need slightly different spacing; prefer composable variants.
- When modifying an established component, check its other usages before changing its public API.
- For every UI task, verify loading, empty, error, disabled, selected, hover/focus, and responsive states where relevant.
