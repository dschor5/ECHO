---
layout: default
title: Communication Delay Settings
section: administrators
permalink: /administration/admin-delay/
---

# Communication Delay Settings

Communication delay is a fundamental aspect of analog mission simulations. ECHO supports three flexible delay modes that allow you to simulate realistic communication constraints between the habitat and mission control, or use any custom delay profile for your specific mission design.

## Overview

The delay represents the **One-Way Light Time (OWLT)** - the time it takes for a message to travel one direction:

- Message sent from Habitat → arrives at MCC after delay seconds
- Message sent from MCC → arrives at Habitat after delay seconds

For example, with a 600-second (10-minute) OWLT:
- HAB sends message at 10:00
- MCC receives message at 10:10
- MCC sends response at 10:15
- HAB receives response at 10:25
- Total round-trip time: 20 minutes

---

## Delay Modes

ECHO offers three delay modes to suit different mission scenarios:

### 1. Fixed Delay

Use a constant, unchanging delay throughout the entire mission. Perfect for training sessions, real-time operations, or any scenario with stable communication.

#### When to Use Fixed Delay

- **Training missions**: Learn procedures with known, predictable delays
- **Real-time communication**: 0 second delay for immediate messaging
- **Earth orbit operations**: Stable delays for LEO (~0-2 seconds) or GEO (~0.25 seconds)
- **Lunar operations**: Stable 1.3 second delay
- **Mars operations**: Fixed delay representing a snapshot of Mars position (270-1200+ seconds)
- **Testing**: Consistent conditions for scientific studies

#### Configuring Fixed Delay

1. Log in as administrator
2. Click **Administration** → **Communication Delay Settings**
3. Select **Manual Delay** mode
4. Enter the delay in seconds
5. Click **Save**

**Examples**:
- `0` - Real-time communication (no delay)
- `1` - Lunar communication
- `600` - 10 minute one-way (Mars typical)
- `1200` - 20 minute one-way (Mars far side)

---

### 2. Piece-wise Function of Time

Implement a time-varying delay that changes according to custom equations. This is the most powerful mode, allowing you to model:

- Delays that increase/decrease over time (e.g., spacecraft moving away/toward)
- Periodic delays (e.g., orbital mechanics)
- Step changes (e.g., mode transitions)
- Any arbitrary mathematical function of mission elapsed time

#### When to Use Piece-wise Delay

- **Trajectory simulations**: Model real spacecraft motion
- **Research studies**: Test how humans adapt to changing delays
- **Realistic Mars missions**: Simulate Earth-Mars relative motion
- **Complex scenarios**: Any delay profile not fitting fixed or current Mars delay
- **Custom training**: Design specific challenge scenarios

#### How It Works

The delay is defined as a function of **Mission Elapsed Time (MET)**, which is the number of seconds since the Mission Start Date.

You define the function as a series of time intervals, each with an equation:

```
[Timestamp] -> [Equation]
2024-03-21 08:00:00 -> 600              (start with 600 sec)
2024-03-22 08:00:00 -> 600 + time/3600   (increase 1 sec per hour)
2024-03-30 08:00:00 -> 0                 (go to real-time)
```

#### Configuring Piece-wise Delay

1. Log in as administrator
2. Click **Administration** → **Communication Delay Settings**
3. Select **Piece-wise Function of Time** mode
4. Enter each interval as a new row:
   - **Timestamp**: When this equation becomes active (format: YYYY-MM-DD HH:MM:SS)
   - **Equation**: Mathematical expression using `time` as variable

5. Click **Save**

#### Time Variable

The `time` variable represents **Mission Elapsed Time (MET) in seconds** since the Mission Start Date.

**Examples**:
- `time = 0` - Start of mission
- `time = 3600` - 1 hour after mission start
- `time = 86400` - 1 day after mission start

#### Supported Mathematical Functions

In your equations, you can use:

**Basic Operations**:
- `+` Addition
- `-` Subtraction
- `*` Multiplication
- `/` Division
- `^` or `**` Exponentiation
- `()` Parentheses for grouping

**Mathematical Functions**:
- `abs(x)` - Absolute value
- `sin(x)` - Sine (radians)
- `cos(x)` - Cosine (radians)
- `tan(x)` - Tangent (radians)
- `exp(x)` - e raised to power
- `log(x)` - Natural logarithm
- `log10(x)` - Base-10 logarithm
- `sqrt(x)` - Square root
- `ceil(x)` - Round up
- `floor(x)` - Round down
- `min(x, y)` - Minimum of two values
- `max(x, y)` - Maximum of two values

**Constants**:
- `pi` - π (3.14159...)
- `e` - Euler's number (2.71828...)

#### Equation Examples

**Linear increase** (delay grows 1 second per second):
```
delay = time
```

**Logarithmic growth** (rapid start, then levels off):
```
delay = log10(time + 1) * 600
```

**Sinusoidal oscillation** (periodic communication window):
```
delay = 600 + 300 * sin(time * 2*pi / 86400)
```

**Piecewise steps** (change delay at specific times):
```
2024-03-21 08:00:00 -> 0
2024-03-22 08:00:00 -> 300
2024-03-23 08:00:00 -> 600
2024-03-24 08:00:00 -> 900
```

**Ramp function** (linear increase from 0 to target):
```
2024-03-21 08:00:00 -> time / 3600 * 100  (increase 100 sec per hour)
2024-03-25 08:00:00 -> 9600               (cap at 160 minutes)
```

#### Practical Example: Spacecraft Journey

Simulating a one-way spacecraft trip from Earth to Mars:

```
2024-03-21 08:00:00 -> time / 86400 * 50   (0-50 min, varies with distance)
2024-08-21 08:00:00 -> 1200                (arrival, ~20 min one-way)
```

---

### 3. Current Mars Delay

Use real-world, pre-calculated communication delays between Earth and Mars based on current planetary positions.

#### When to Use Current Mars Delay

- **Mars simulation training**: Realistic Mars mission conditions
- **Educational missions**: Teach about actual Mars operations
- **Current events**: Use "now" for timely Earth-Mars scenarios
- **Science studies**: Research based on real Mars OWLT data

#### How It Works

ECHO includes actual Mars communication delay data from 2020 to 2039. The system:

1. Reads the current date/time from your server
2. Looks up the Earth-Mars distance at that time
3. Calculates the resulting communication delay (distance ÷ speed of light)
4. Updates approximately every 4 hours

The delays range from ~270 seconds (Mars closest to Earth) to ~1200+ seconds (Mars farthest).

#### Configuring Current Mars Delay

1. Log in as administrator
2. Click **Administration** → **Communication Delay Settings**
3. Select **Current Mars Delay** mode
4. Click **Save**

That's it! The delay automatically updates based on actual Earth-Mars positions.

#### Data Characteristics

- **Date range**: 2020-2039
- **Update frequency**: ~4-hour intervals
- **Minimum delay**: ~270 seconds (3 AU, closest approach)
- **Maximum delay**: ~1200+ seconds (2.67 AU, farthest)
- **Accuracy**: Based on historical NASA/JPL ephemeris data

#### Example Delays

Real Earth-Mars OWLT delays over time:

| Date | Delay (sec) | Distance |
|------|------------|----------|
| 2024-03-21 | 600 | ~4.0 AU |
| 2024-08-21 | 1200+ | ~2.2 AU (closest) |
| 2025-01-21 | 300 | ~2.0 AU |
| 2025-10-21 | 900 | ~1.4 AU |

---

## Dissertation Example

The following example is from the original ECHO dissertation research. It demonstrates a complex, realistic delay profile:

### Mathematical Definition

$$
delay(t_{MET})=
\begin{cases}
\frac{t_{MET}}{3600}-20 & \text{for } 2023\text{-}12\text{-}19 \le t_{MET} \le 2023\text{-}12\text{-}20 \\
\log_{10}(t_{MET}-86400\cdot 2) & \text{for } 2023\text{-}12\text{-}20 \le t_{MET} \le 2023\text{-}12\text{-}21 \\
-20\sin\left(t_{MET}\cdot \frac{2\pi}{86400}\right) & \text{for } 2023\text{-}12\text{-}21 \le t_{MET} \le 2023\text{-}12\text{-}22 \\
10 & \text{for } 2023\text{-}12\text{-}22 \le t_{MET} \le 2023\text{-}12\text{-}23 \\
0 & \text{otherwise}
\end{cases}
$$

### UI Configuration

Enter these rows in piece-wise function mode:

```
2023-12-19 08:00:00 -> time/3600 - 20
2023-12-20 08:00:00 -> log10(time - 172800)
2023-12-21 08:00:00 -> -20 * sin(time * (2*pi/86400))
2023-12-22 08:00:00 -> 10
2023-12-23 08:00:00 -> 0
```

### Visualization

![Communication delay settings](../static/s12-admin-plot-delay.png)

This example shows:
1. **Linear ramp**: Delay increases gradually (training phase)
2. **Logarithmic growth**: Delay changes shape (natural system evolution)
3. **Sinusoidal oscillation**: Periodic delay pattern (orbit-based)
4. **Constant plateau**: Stable delay for operations
5. **Zero delay**: Real-time communication for final phase

---

## Related Documentation

- [Mission Settings]({{ '/administration/admin-mission/' | relative_url }}) - Configure Mission Start Date
- [Installation]({{ '/installation/' | relative_url }}) - Server time configuration
- [About ECHO]({{ '/about/' | relative_url }}) - Research and missions using ECHO

