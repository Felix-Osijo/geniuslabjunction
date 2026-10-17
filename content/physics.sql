SET NAMES utf8mb4;

INSERT INTO lessons (subject_slug, slug, title, summary, body, sort_order) VALUES
('physics', 'motion', 'Motion: Speed, Velocity and Acceleration',
 'How to describe how fast something moves and how its motion changes.',
 '## Distance and displacement
Distance is the total length of the path travelled. It is a scalar, so it has size only. Displacement is the straight-line distance from the start to the finish in a stated direction. It is a vector, so it has size and direction.

## Speed and velocity
Speed is the rate of change of distance:
speed = distance / time
Velocity is the rate of change of displacement:
velocity = displacement / time
The SI unit of both is metres per second (m/s).

## Acceleration
Acceleration is the rate of change of velocity:
a = (v - u) / t
Here u is the initial velocity, v is the final velocity and t is the time taken. The SI unit is m/s². A negative acceleration is called retardation or deceleration.

## Equations of uniformly accelerated motion
- v = u + at
- s = ut + ½at²
- v² = u² + 2as

In these equations, s is the distance covered in the direction of motion.

## Worked example
A car starts from rest and accelerates uniformly at 2 m/s² for 5 s. Find its final velocity and the distance covered.
v = u + at = 0 + (2 × 5) = 10 m/s
s = ut + ½at² = 0 + ½ × 2 × 5² = 25 m', 1),

('physics', 'newtons-laws', 'Newton''s Laws of Motion',
 'Inertia, force and acceleration, and action and reaction.',
 '## First law (law of inertia)
A body stays at rest, or keeps moving in a straight line at constant speed, unless an external force acts on it. Inertia is the reluctance of a body to change its state of rest or motion. The greater the mass, the greater the inertia.

## Second law
The rate of change of momentum of a body is directly proportional to the applied force and takes place in the direction of the force. For a body of constant mass this gives:
F = ma
Force is measured in newtons (N). One newton is the force that gives a mass of 1 kg an acceleration of 1 m/s².

## Third law
To every action there is an equal and opposite reaction. The two forces act on different bodies, so they do not cancel each other.

## Momentum
momentum = mass × velocity
The SI unit of momentum is kg m/s.

## Worked example
A force of 20 N acts on a 4 kg block on a smooth surface. Find its acceleration.
a = F / m = 20 / 4 = 5 m/s²', 2),

('physics', 'work-energy-power', 'Work, Energy and Power',
 'How force, distance and time connect to energy and power.',
 '## Work
Work is done when a force moves its point of application in the direction of the force.
work = force × distance moved in the direction of the force
W = F × d
The SI unit of work is the joule (J). One joule is the work done when a force of 1 N moves a body through 1 m in the direction of the force.

## Energy
Energy is the ability to do work and is also measured in joules. Two common mechanical forms are:
- Kinetic energy, the energy a body has because of its motion: KE = ½mv²
- Potential energy, the energy a body has because of its position: PE = mgh

The principle of conservation of energy states that energy cannot be created or destroyed. It can only change from one form to another.

## Power
Power is the rate of doing work:
P = W / t
The SI unit of power is the watt (W). One watt is one joule per second.

## Worked example
A boy of mass 50 kg climbs a height of 4 m in 10 s. Take g = 10 m/s².
Work done = mgh = 50 × 10 × 4 = 2000 J
Power = W / t = 2000 / 10 = 200 W', 3);

INSERT INTO quiz_questions (lesson_id, question, option_a, option_b, option_c, option_d, correct_option, explanation, sort_order) VALUES
((SELECT id FROM lessons WHERE subject_slug = 'physics' AND slug = 'motion'),
 'A car travels 120 km in 2 hours. What is its average speed?',
 '60 km/h', '240 km/h', '122 km/h', '118 km/h', 'A',
 'Speed = distance / time = 120 / 2 = 60 km/h.', 1),
((SELECT id FROM lessons WHERE subject_slug = 'physics' AND slug = 'motion'),
 'Which of these is a vector quantity?',
 'Speed', 'Distance', 'Displacement', 'Time', 'C',
 'Displacement has both size and direction. Speed, distance and time have size only.', 2),
((SELECT id FROM lessons WHERE subject_slug = 'physics' AND slug = 'motion'),
 'A body accelerates uniformly from 4 m/s to 16 m/s in 6 s. What is its acceleration?',
 '1 m/s²', '2 m/s²', '3 m/s²', '12 m/s²', 'B',
 'a = (v - u) / t = (16 - 4) / 6 = 2 m/s².', 3),
((SELECT id FROM lessons WHERE subject_slug = 'physics' AND slug = 'motion'),
 'A body starts from rest and accelerates at 3 m/s². How far does it travel in 4 s?',
 '12 m', '24 m', '48 m', '6 m', 'B',
 's = ut + ½at² = 0 + ½ × 3 × 4² = 24 m.', 4),

((SELECT id FROM lessons WHERE subject_slug = 'physics' AND slug = 'newtons-laws'),
 'The tendency of a body to resist a change in its state of rest or of uniform motion is called:',
 'Momentum', 'Inertia', 'Friction', 'Weight', 'B',
 'This is inertia, the idea behind the first law of motion.', 1),
((SELECT id FROM lessons WHERE subject_slug = 'physics' AND slug = 'newtons-laws'),
 'A force of 30 N gives an object an acceleration of 6 m/s². What is the mass of the object?',
 '5 kg', '180 kg', '0.2 kg', '24 kg', 'A',
 'm = F / a = 30 / 6 = 5 kg.', 2),
((SELECT id FROM lessons WHERE subject_slug = 'physics' AND slug = 'newtons-laws'),
 'A gun recoils backwards when it is fired. Which law of motion explains this?',
 'The first law', 'The second law', 'The third law', 'The law of conservation of mass', 'C',
 'The bullet is pushed forward and the gun is pushed back with an equal and opposite force.', 3),
((SELECT id FROM lessons WHERE subject_slug = 'physics' AND slug = 'newtons-laws'),
 'What is the momentum of a 5 kg mass moving at 4 m/s?',
 '1.25 kg m/s', '9 kg m/s', '20 kg m/s', '80 kg m/s', 'C',
 'momentum = mass × velocity = 5 × 4 = 20 kg m/s.', 4),

((SELECT id FROM lessons WHERE subject_slug = 'physics' AND slug = 'work-energy-power'),
 'What is the SI unit of power?',
 'Joule', 'Newton', 'Watt', 'Pascal', 'C',
 'Power is measured in watts. One watt is one joule per second.', 1),
((SELECT id FROM lessons WHERE subject_slug = 'physics' AND slug = 'work-energy-power'),
 'What is the kinetic energy of a 2 kg mass moving at 3 m/s?',
 '3 J', '6 J', '9 J', '18 J', 'C',
 'KE = ½mv² = ½ × 2 × 3² = 9 J.', 2),
((SELECT id FROM lessons WHERE subject_slug = 'physics' AND slug = 'work-energy-power'),
 'A force of 10 N moves a body 5 m in the direction of the force. How much work is done?',
 '2 J', '15 J', '50 J', '500 J', 'C',
 'W = F × d = 10 × 5 = 50 J.', 3),
((SELECT id FROM lessons WHERE subject_slug = 'physics' AND slug = 'work-energy-power'),
 'A machine does 600 J of work in 20 s. What is its power?',
 '30 W', '120 W', '12000 W', '0.03 W', 'A',
 'P = W / t = 600 / 20 = 30 W.', 4);
