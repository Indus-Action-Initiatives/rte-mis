import './main-nav.scss';
import template from './main-nav.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/Main Nav',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    search_placeholder: { control: 'text' },
  },
};

export const Default = {
  name: 'Default',
  args: {
    menu_links: [
      { title: 'Home', url: '#', active: false },
      { title: 'About Us', url: '#', active: false },
      { title: 'Find Schools', url: '#', active: false },
      { title: 'Track Application', url: '#', active: false },
      { title: 'FAQs', url: '#', active: false },
      { title: 'Grievance', url: '#', active: false },
    ],
    search_placeholder: 'Search Schools',
  }
};
