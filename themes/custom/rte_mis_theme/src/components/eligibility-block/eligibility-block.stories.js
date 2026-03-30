import './eligibility-block.scss';
import template from './eligibility-block.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/Eligibility Block',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    title: { control: 'text' },
    class_ages: { control: 'object' },
    eligibility: { control: 'object' },
    priorities: { control: 'object' },
  },
};

export const Default = {
  name: 'Default',
  args: {
    title: 'Eligibility Criteria',
    class_ages: [
      'Pre-Primary : 3 to 5 years',
      'Class 1 : 5 to 7 years'
    ],
    eligibility: [
      'Weaker section: Family Annual income less than 800,000 per annum',
      'Scheduled Caste, Backward Class/Other Backward Class (non-creamy layer)',
      'War widows\' children and Destitute parents\' children ( minimum 50% disability)'
    ],
    priorities: [
      '1st Priority : Children residing within a 1 km radius of the school.',
      '2nd Priority : Children residing within a radius of 3 km.',
      '3rd Priority : In case of unfilled vacancies, children residing beyond 3 km but within 6 km radius.'
    ],
  }
};

export const LongContent = {
  name: 'Long Content',
  args: {
    title: 'Eligibility Criteria (Detailed)',
    class_ages: [
      'Pre-Primary (Nursery/LKG/UKG) : Age must be between 3 and 5 years as of March 31st.',
      'Class 1 (Primary) : Age must be between 5 and 7 years as of March 31st.'
    ],
    eligibility: [
      'Detailed eligibility condition 1 with more information to see how it wraps and looks in the list',
      'Detailed eligibility condition 2',
      'Detailed eligibility condition 3',
      'Detailed eligibility condition 4'
    ],
    priorities: [
      'Priority 1 detailed explanation of how school allotment works for applicants within the primary radius',
      'Priority 2 detailed explanation'
    ],
  }
};