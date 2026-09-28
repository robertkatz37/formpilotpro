# FormPilot Pro 10.19.0

- Redesigned multi-step indicators to match the approved wizard design.
- Added Progress Display selector: Step Nodes + Progress Bar, Step Nodes Only, Progress Bar Only, or None.
- Step Nodes render at the top with active/completed states and connecting lines.
- Combined mode renders a slim bottom progress bar with Overall Completion metadata.
- Progress-Bar-only mode renders the larger inline status bar with Step X of Y and percentage overlay.
- Progress calculation follows the approved flow: Step 1 starts at 0%, intermediate steps advance by completed-step fraction, and successful submission reaches 100%.
- Preserved existing colors, sizes, responsive controls, navigation labels, validation, AJAX submission, and field state.
- Added compatibility mapping for older Step Numbers/Navigational Tabs settings.
- New multi-step templates default to the combined Step Nodes + Progress Bar presentation.
