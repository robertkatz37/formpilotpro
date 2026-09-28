# FormPilot Pro 10.18.0

- Fixed multi-step percentage progress: Step 1 of 3 starts at 33%, Step 2 at 67%, final step at 100%.
- Prevented progress metadata from being styled as the progress bar; progress track/bar are isolated and responsive.
- Added explicit progress ARIA values and important width handling to avoid theme/plugin CSS overrides.
- Fixed frontend form-title rendering fallback and visibility; blank display title now falls back to the internal form name.
- Added responsive device controls in Form Builder: Desktop, Tablet, Mobile.
- Added saved per-device responsive settings for form padding/radius, gaps, title/description/label sizes, input sizing, button sizing, and multi-step indicator sizing.
- Added Desktop/Tablet/Mobile live-preview switcher.
- Added mobile-specific step-title visibility and responsive multi-step layout.
- Responsive settings are sanitized server-side and stored per form.
