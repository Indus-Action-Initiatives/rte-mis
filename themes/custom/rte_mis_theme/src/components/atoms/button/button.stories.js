import { fn } from 'storybook/test';

import './button.scss';
import template from './button.twig';
import { renderTwig } from '../../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/Button',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    label: {
      control: 'text',
      description: 'The button text',
    },
    variant: {
      control: { type: 'select' },
      options: ['primary', 'secondary'],
      description: 'Visual style of the button',
    },
    size: {
      control: { type: 'select' },
      options: ['small', 'medium', 'large'],
      description: 'Size of the button',
    },
    disabled: {
      control: 'boolean',
      description: 'Whether the button is disabled',
    },
    url: {
      control: 'text',
      description: 'Optional URL (renders as a link)',
    },
    // Color overrides
    bg_color: {
      control: 'color',
      description: 'Custom background color (overrides variant default)',
    },
    color: {
      control: 'color',
      description: 'Custom text color (overrides variant default)',
    },
    border_color: {
      control: 'color',
      description: 'Custom border color',
    },
    hover_bg_color: {
      control: 'color',
      description: 'Custom hover background color',
    },
    onClick: { action: 'onClick' },
  },
  args: {
    onClick: fn(),
  },
};

export const Primary = {
  args: {
    label: 'Button',
    variant: 'primary',
  },
};

export const Secondary = {
  args: {
    label: 'Button',
    variant: 'secondary',
  },
};

export const Large = {
  args: {
    label: 'Large Button',
    variant: 'primary',
    size: 'large',
  },
};

export const Small = {
  args: {
    label: 'Small Button',
    variant: 'primary',
    size: 'small',
  },
};

export const Disabled = {
  args: {
    label: 'Disabled',
    variant: 'primary',
    disabled: true,
  },
};

export const AsLink = {
  args: {
    label: 'Link Button',
    variant: 'primary',
    url: '#',
  },
};

export const CustomColor = {
  name: 'Custom Color',
  args: {
    label: 'Apply Now',
    variant: 'primary',
    size: 'medium',
    url: '#apply',
    bg_color: '#f97316',
    color: '#ffffff',
    border_color: '#ea580c',
    hover_bg_color: '#ea580c',
  },
};

