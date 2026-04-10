import './faq-section.scss';
import template from './faq-section.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/FAQ Section',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    title: { control: 'text' },
    items: { control: 'object' },
  },
};

const defaultItems = [
  {
    question: 'Who is eligible for RTE admission?',
    answer: 'Children belonging to weaker sections and disadvantaged groups as detailed by the government notification are eligible for admission under the RTE quota.',
    open: true,
  },
  {
    question: 'What documents are required for application?',
    answer: 'You will need proof of age (birth certificate), proof of residence, caste certificate (if applicable), and income certificate.',
    open: false,
  },
  {
    question: 'How do I track my application status?',
    answer: 'You can log into the portal using your application number and password to view real-time updates regarding your registration status.',
    open: false,
  },
];

export const Default = {
  args: {
    title: 'Frequently Asked Questions',
    items: defaultItems,
  },
};
