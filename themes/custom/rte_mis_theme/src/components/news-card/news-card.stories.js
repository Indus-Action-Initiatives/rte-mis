import './news-card.scss';
import './news-card.js';
import template from './news-card.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/News Card',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    title: { control: 'text' },
    date: { control: 'text' },
    description: { control: 'text' },
    image: { control: 'text' },
    url: { control: 'text' },
  },
};

export const Default = {
  args: {
    title: 'Guidelines For Implementation Of Section 12(1)(C) Of "The Right Of Children To Free And Compulsory Education Act 2009" In The State Of Maharashtra.',
    date: 'December 26, 2025',
    description: '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi tristique sapien vitae accumsan ultrices. Ut in vehicula dolor, et facilisis metus. Curabitur vel leo id urna rutrum aliquam.</p><p>Nulla ullamcorper, erat sed facilisis lacinia, eros felis eleifend leo, vitae condimentum lorem tortor ac est. Fusce tristique finibus odio nec cursus. Donec vitae ligula euismod, sollicitudin urna id, fringilla urna.</p>',
    image: 'https://images.unsplash.com/photo-1509062522246-3755977927d7?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
    url: '#',
  },
};
