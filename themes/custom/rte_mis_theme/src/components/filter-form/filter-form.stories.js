import template from './filter-form.twig';
import './filter-form.scss';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
    title: 'Components/Filter Form',
    tags: ['autodocs'],
    render: (args) => renderTwig(template, args),

    argTypes: {
        tabs: {
            control: 'object',
            description: 'Array of tab/filter buttons (e.g. language tabs)',
        },
        tabs_label: {
            control: 'text',
            description: 'Accessible label for the tab group',
        },
        search: {
            control: 'object',
            description: 'Search field config: { placeholder, name, value }',
        },
        sort: {
            control: 'object',
            description: 'Sort dropdown config: { name, options: [{value, label, selected}] }',
        },
    },
};

// ── Default: Tabs + Search + Sort (matches the screenshot) ───

export const Default = {
    args: {
        tabs_label: 'Filter by language',
        tabs: [
            { value: 'all',   label: 'All',    active: true  },
            { value: 'en',    label: 'English', active: false },
            { value: 'hi',    label: 'Hindi',   active: false },
            { value: 'mr',    label: 'मराठी',    active: false },
        ],
        search: {
            placeholder: 'Search for keywords...',
            name: 'search',
            value: '',
        },
        sort: {
            name: 'sort_by',
            options: [
                { value: '',       label: 'Sort By', selected: true  },
                { value: 'newest', label: 'Newest',  selected: false },
                { value: 'oldest', label: 'Oldest',  selected: false },
                { value: 'az',     label: 'A – Z',   selected: false },
            ],
        },
    },
};

// ── Tabs Only ────────────────────────────────────────────────

export const TabsOnly = {
    args: {
        tabs_label: 'Filter by language',
        tabs: [
            { value: 'all', label: 'All',     active: true  },
            { value: 'en',  label: 'English', active: false },
            { value: 'hi',  label: 'Hindi',   active: false },
            { value: 'mr',  label: 'मराठी',    active: false },
        ],
    },
};

// ── Search + Sort Only ───────────────────────────────────────

export const SearchAndSort = {
    args: {
        search: {
            placeholder: 'Search for keywords...',
            name: 'search',
            value: '',
        },
        sort: {
            name: 'sort_by',
            options: [
                { value: '',       label: 'Sort By', selected: true  },
                { value: 'newest', label: 'Newest',  selected: false },
                { value: 'oldest', label: 'Oldest',  selected: false },
            ],
        },
    },
};

// ── Hindi Selected ───────────────────────────────────────────

export const HindiSelected = {
    args: {
        tabs_label: 'Filter by language',
        tabs: [
            { value: 'all', label: 'All',     active: false },
            { value: 'en',  label: 'English', active: false },
            { value: 'hi',  label: 'Hindi',   active: true  },
            { value: 'mr',  label: 'मराठी',    active: false },
        ],
        search: {
            placeholder: 'Search for keywords...',
            name: 'search',
            value: '',
        },
        sort: {
            name: 'sort_by',
            options: [
                { value: '',       label: 'Sort By', selected: true  },
                { value: 'newest', label: 'Newest',  selected: false },
                { value: 'oldest', label: 'Oldest',  selected: false },
            ],
        },
    },
};
