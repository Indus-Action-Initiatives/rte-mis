import './testimonial-items.scss';
import './testimonial-items.js';
import template from './testimonial-items.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/Testimonial Items',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  parameters: {
    layout: 'fullscreen',
  },
};

export const Default = {
  args: {
    title: 'Success Stories',
    subtitle: 'Hear from families who successfully enrolled through RTE',
    items: [
      {
        image: 'https://i.pravatar.cc/150?u=amit',
        name: 'Amit Sharma',
        meta: 'F/O Ajay',
        location: 'Surat, Surat District',
        feedback: 'Thanks to the right to education, i was able to pursue my dreams and become the first person in my family to attend college. Education opened doors for me that I never thought possible, and i am forever grateful to the RTE program for changing the course of my life.',
      },
      {
        image: 'https://i.pravatar.cc/150?u=rakesh',
        name: 'Rakesh Verma',
        meta: 'F/O Ajay',
        location: 'Indore, Indore District',
        feedback: 'Thanks to the right to education, i was able to pursue my dreams and become the first person in my family to attend college. Education opened doors for me that I never thought possible, and i am forever...',
      },
      {
        image: 'https://i.pravatar.cc/150?u=anjali',
        name: 'Anjali Nair',
        meta: 'M/O Sareeta',
        location: 'Kochi, Ernakulam District',
        feedback: 'Thanks to the right to education, i was able to pursue my dreams and become the first person in my family to attend college. Education opened doors for me that I never thought possible, and i am forever...',
      },
      {
        image: 'https://i.pravatar.cc/150?u=anjali',
        name: 'Anjali Nair',
        meta: 'M/O Sareeta',
        location: 'Kochi, Ernakulam District',
        feedback: 'Thanks to the right to education, i was able to pursue my dreams and become the first person in my family to attend college. Education opened doors for me that I never thought possible, and i am forever...',
      },
      {
        image: 'https://i.pravatar.cc/150?u=amit',
        name: 'Amit Sharma',
        meta: 'F/O Ajay',
        location: 'Surat, Surat District',
        feedback: 'Thanks to the right to education, i was able to pursue my dreams and become the first person in my family to attend college. Education opened doors for me that I never thought possible, and i am forever grateful to the RTE program for changing the course of my life.',
      },
    ],
  },
};
