import './header-branding.scss';
import template from './header-branding.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/Header Branding',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    department_name: { control: 'text' },
    portal_name: { control: 'text' },
  },
};

export const Default = {
  name: 'Default',
  args: {
    logos: [
      {
        src: 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/55/Emblem_of_India.svg/330px-Emblem_of_India.svg.png',
        alt: 'Government of Maharashtra',
        width: '40',
      },
      {
        src: 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c5/RTE_Logo.svg/2560px-RTE_Logo.svg.png',
        alt: 'RTE Portal Logo',
        width: '48',
      }
    ],
    department_name: 'School Education Department',
    portal_name: 'Right to Education (RTE) Portal',
  }
};
