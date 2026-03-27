import './icon-button.scss';
import template from './icon-button.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Atoms/Icon Button',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    icon: {
      control: 'select',
      options: ['accessibility', 'user_plus', 'search', 'settings', 'close', 'menu'],
    },
    size: {
      control: 'radio',
      options: ['small', 'medium', 'large'],
    },
    variant: {
      control: 'radio',
      options: ['primary', 'secondary', 'outline'],
    },
    url: { control: 'text' },
    aria_label: { control: 'text' },
  },
};

export const Accessibility = {
  args: {
    icon: 'accessibility',
    aria_label: 'Accessibility options',
    size: 'medium',
    variant: 'outline',
  },
};

export const UserPlus = {
  args: {
    icon: 'user_plus',
    aria_label: 'Add user',
    size: 'medium',
    variant: 'outline',
  },
};

export const PrimarySmall = {
  args: {
    icon: 'search',
    aria_label: 'Search',
    size: 'small',
    variant: 'primary',
  },
};
