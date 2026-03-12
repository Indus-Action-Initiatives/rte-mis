import './stat-card.scss';
import template from './stat-card.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

// Group template wrapping 4 stat cards together
const groupTemplate = (cards) => `
  <div class="c-stat-card-group">
    <div class="c-stat-card-group__inner">
      ${cards.join('')}
    </div>
  </div>
`;

const renderCard = (args) => renderTwig(template, args);

export default {
    title: 'Components/Stat Card',
    tags: ['autodocs'],
    render: (args) => renderCard(args),
    parameters: {
        layout: 'fullscreen',
        backgrounds: {
            default: 'blue',
            values: [
                { name: 'blue', value: '#1565e0' },
                { name: 'light', value: '#f4f4f4' },
            ],
        },
        docs: {
            description: {
                component:
                    'Statistics counter card with an icon, large numeric value, and label. Designed for a blue gradient hero banner row. Use `.c-stat-card-group` to wrap 4 cards in the full-width container.',
            },
        },
    },
    argTypes: {
        value: {
            control: 'text',
            description: 'The stat number (e.g. "1,11,100+")',
        },
        label: {
            control: 'text',
            description: 'Descriptive label',
        },
        icon: {
            control: { type: 'select' },
            options: ['school', 'staff', 'students', 'districts', 'default'],
            description: 'Icon to display',
        },
    },
};

/* ---- Individual card stories ---- */
export const Schools = {
    name: 'Registered Schools',
    args: {
        value: '1,11,100+',
        label: 'Registered Schools',
        icon: 'school',
    },
};

export const Staff = {
    name: 'Total Staff',
    args: {
        value: '8,37,656',
        label: 'Total Staff',
        icon: 'staff',
    },
};

export const Students = {
    name: 'Enrolled Students',
    args: {
        value: '2,02,85,525',
        label: 'Enrolled Students',
        icon: 'students',
    },
};

export const Districts = {
    name: 'Districts Covered',
    args: {
        value: '36',
        label: 'Districts Covered',
        icon: 'districts',
    },
};

/* ---- Full row (group) story ---- */
export const FullRow = {
    name: 'Full Row (all 4 stats)',
    parameters: {
        backgrounds: { disable: true },
    },
    render: () => groupTemplate([
        renderCard({ value: '1,11,100+', label: 'Registered Schools', icon: 'school' }),
        renderCard({ value: '8,37,656', label: 'Total Staff', icon: 'staff' }),
        renderCard({ value: '2,02,85,525', label: 'Enrolled Students', icon: 'students' }),
        renderCard({ value: '36', label: 'Districts Covered', icon: 'districts' }),
    ]),
};
