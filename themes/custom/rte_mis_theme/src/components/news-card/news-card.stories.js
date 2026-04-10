import './news-card.scss';
import template from './news-card.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/News Card',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    title: { control: 'text' },
    date: { control: 'text' },
    image: { control: 'text' },
    url: { control: 'text' },
  },
};

export const Default = {
  args: {
    title: 'Guidelines For Implementation Of Section 12(1)(C) Of "The Right Of Children To Free And Compulsory Education Act 2009" In The State Of Maharashtra.',
    date: 'December 26, 2025',
    image: 'https://images.unsplash.com/photo-1509062522246-3755977927d7?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
    url: '#',
  },
};
