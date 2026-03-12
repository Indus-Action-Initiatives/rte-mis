import './container.scss';
import '../stat-card/stat-card.scss';
import template from './container.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

const sampleContent = `
  <p style="margin:0;font-size:1rem;line-height:1.6">
    This is content inside the container. It is constrained by
    <strong>max-width</strong> and padded by the container's spacing modifiers.
  </p>
`;

// Stat card data — matches Figma screenshot
const statItems = [
    { value: '1,11,100+', label: 'Registered Schools', icon: 'school' },
    { value: '8,37,656', label: 'Total Staff', icon: 'staff' },
    { value: '2,02,85,525', label: 'Enrolled Students', icon: 'students' },
    { value: '36', label: 'Districts Covered', icon: 'districts' },
];

export default {
    title: 'Layout/Container',
    tags: ['autodocs'],
    render: (args) => renderTwig(template, args),
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component: `
A full-width section wrapper with configurable background color, inner max-width,
and padding. Supports a **\`content\`** slot for arbitrary HTML or an **\`items\`** array
for automatic component includes via:

\`\`\`twig
{% include 'component:stat-card' with { value: '...', label: '...', icon: '...' } only %}
\`\`\`
        `,
            },
        },
    },
    argTypes: {
        bg_color: {
            control: { type: 'select' },
            options: ['white', 'light', 'blue', 'dark', 'blue-gradient', 'transparent'],
        },
        max_width: {
            control: { type: 'select' },
            options: ['sm', 'md', 'lg', 'xl', 'full'],
        },
        padding_y: {
            control: { type: 'select' },
            options: ['none', 'sm', 'md', 'lg', 'xl'],
        },
        padding_x: {
            control: { type: 'select' },
            options: ['none', 'sm', 'md', 'lg'],
        },
        tag: {
            control: { type: 'select' },
            options: ['div', 'section', 'article', 'main', 'aside', 'header', 'footer'],
        },
    },
};

export const White = {
    name: 'Background: White',
    args: { bg_color: 'white', max_width: 'xl', padding_y: 'md', padding_x: 'md', tag: 'section', content: sampleContent },
};

export const Light = {
    name: 'Background: Light',
    args: { ...White.args, bg_color: 'light' },
};

export const Blue = {
    name: 'Background: Blue',
    args: { ...White.args, bg_color: 'blue' },
};

export const Dark = {
    name: 'Background: Dark',
    args: { ...White.args, bg_color: 'dark' },
};

// ── Component include demo ──────────────────────────────────────────────────
// Uses {% include 'component:stat-card' %} inside container.twig
// ───────────────────────────────────────────────────────────────────────────
export const WithStatCards = {
    name: '🧩 Include: Stat Cards (blue-gradient)',
    parameters: {
        docs: {
            description: {
                story:
                    'Demonstrates **Twig component includes** — the `container` template uses ' +
                    "`{% include 'component:stat-card' with { ... } only %}` to render each card.",
            },
        },
    },
    args: {
        bg_color: 'blue-gradient',
        max_width: 'xl',
        padding_y: 'lg',
        padding_x: 'md',
        tag: 'section',
        items: statItems,
    },
};

export const NarrowContent = {
    name: 'Narrow (max-width: md)',
    args: { ...White.args, bg_color: 'light', max_width: 'md', padding_y: 'lg' },
};

export const NoPadding = {
    name: 'No Padding',
    args: { ...White.args, bg_color: 'light', padding_y: 'none', padding_x: 'none' },
};
