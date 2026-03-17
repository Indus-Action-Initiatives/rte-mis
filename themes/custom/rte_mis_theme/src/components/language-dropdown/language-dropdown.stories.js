import template from './language-dropdown.twig';
import './language-dropdown.scss';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
    title: 'Components/Language Dropdown',
    tags: ['autodocs'],

    render: (args) => renderTwig(template, args),

    argTypes: {
        form_action: {
            control: 'text',
        },
        select_id: {
            control: 'text',
        },
        select_name: {
            control: 'text',
        },
        options: {
            control: 'object',
        },
    },
};

export const Default = {
    args: {
        form_action: '/',
        select_id: 'edit-lang-dropdown-select',
        select_name: 'lang_dropdown_select',
        options: [
            {
                value: 'en',
                label: 'English',
                selected: true,
            },
            {
                value: 'hi',
                label: 'Hindi',
                selected: false,
            },
        ],
    },
};

export const HindiSelected = {
    args: {
        form_action: '/',
        select_id: 'edit-lang-dropdown-select',
        select_name: 'lang_dropdown_select',
        options: [
            {
                value: 'en',
                label: 'English',
                selected: false,
            },
            {
                value: 'hi',
                label: 'Hindi',
                selected: true,
            },
        ],
    },
};