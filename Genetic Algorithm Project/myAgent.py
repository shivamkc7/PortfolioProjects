import math
import matplotlib.pyplot as plt
import numpy as np
import pandas as pd
import random

playerName = "myAgent"
nPercepts = 75  # This is the number of percepts
nActions = 7  # This is the number of actions
avg_fitness_array = list()  # This stores average fitness values
count_or_something = 0  # This is a counter


# This is the class for your creature/agent

class MyCreature:

    def __init__(self):
        # You should initialise self.chromosome member variable here (whatever you choose it
        # to be - a list/vector/matrix of numbers - and initialise it with some random
        # values
        # Initializing chromosome of size 7 x 75 with random integers from -75 to 75 inclusive
        self.chromosome = np.random.randint(-75, 75, size=(7, 75))

    def AgentFunction(self, percepts):

        actions = np.zeros((nActions))
        percept_flatten = percepts.flatten()  # flattening the percepts
        sum = 0
        # Translating from 'percepts' to 'actions' through 'self.chromosome'.
        for i in range(7):
            for j in range(75):
                sum = sum + percept_flatten[j] * self.chromosome[i, j]
            actions[i] = sum

        return actions


def newGeneration(old_population):
    global avg_fitness_array
    global count_or_something
    count_or_something = count_or_something + 1
    # This function should return a list of 'new_agents' that is of the same length as the
    # list of 'old_agents'.  That is, if previous game was played with N agents, the next game
    # should be played with N agents again.

    # This function should also return average fitness of the old_population
    N = len(old_population)

    # Fitness for all agents
    fitness = np.zeros((N))

    # This loop iterates over your agents in the old population - the purpose of this boiler plate
    # code is to demonstrate how to fetch information from the old_population in order
    # to score fitness of each agent
    for n, creature in enumerate(old_population):
        # This fitness functions considers if the creature survived at the end of the game
        # and its energy through its energy-size formula
        fitness[n] = creature.alive + round(math.exp(math.log(2) * creature.size))

    # At this point you should sort the agent according to fitness and create new population

    # Getting the data ready for tournament selection with fitness and chromosome side to side:
    flat_chromosome = np.zeros((N, 525))
    for n in range(N):
        extract_chromosome = np.copy(old_population[n].chromosome)  # get the chromosome from old_population
        flat_chromosome[n] = extract_chromosome.flatten()  # flatten the chromosome
    merged_fit_creature = np.concatenate((fitness.reshape((-1, 1)), flat_chromosome),
                                         axis=1)  # merge fitness and flat_chromosome.

    df = pd.DataFrame(
        data=merged_fit_creature)  # converting numpy array to df where 1st column is fitness and
    # 2nd column to 525th column is flattened chromosome.

    # Elitism:
    sort_df = df.sort_values(by=[0], ascending=False)  # sort the data descending order, according to fitness scores
    new_population = list()
    # The following loop makes sure that the fittest individual from the old population appears 7 times in the
    # new population
    for n in range(7):
        # Create new creature
        new_creature_1 = MyCreature()
        new_creature_flatten_1 = sort_df.iloc[0, 1:526]  # this is a series datatype. Big Question.
        unflatten_new_creature_1 = new_creature_flatten_1.values.reshape(7, 75)  # do i need this?
        new_creature_1.chromosome = unflatten_new_creature_1
        new_population.append(new_creature_1)
    # The following loop creates 27 (N-7) new creatures through crossover and mutation
    for n in range(N - 7):
        # Create new creature
        new_creature = MyCreature()
        sample = df.sample(frac=0.90)  # randomly select 90% of the data

        sort_sample = sample.sort_values(by=[0],
                                         ascending=False)  # sort the data descending order, according to fitness scores

        # Creating the breakpoints for crossover between two fittest parents
        parent_1_non_array_chromosome_1 = sort_sample.iloc[0,
                                          1:301]
        parent_1_non_array_chromosome_2 = sort_sample.iloc[0, 376:451]
        parent_2_non_array_chromosome_1 = sort_sample.iloc[1, 301:376]
        parent_2_non_array_chromosome_2 = sort_sample.iloc[1, 451:526]

        # joining the newly created chromosomes
        new_creature_flatten = pd.concat(
            [parent_1_non_array_chromosome_1, parent_2_non_array_chromosome_1, parent_1_non_array_chromosome_2,
             parent_2_non_array_chromosome_2])

        # Introducing mutation with 10% probability
        x = random.uniform(0, 1)
        if x < 0.10:
            for i in range(1, 526):
                # flip to random value from -75 to 75
                new_creature_flatten[i] = np.random.randint(-75, 75)
        # Reshaping (unflattening) the chromosome to its defauly 7 x 75 shape
        unflatten_new_creature = new_creature_flatten.values.reshape(7, 75)
        new_creature.chromosome = unflatten_new_creature

        # Add the new agent to the new population
        new_population.append(new_creature)

    # At the end you need to compute average fitness and return it along with your new population
    avg_fitness = np.mean(fitness)
    avg_fitness_array.append(avg_fitness)  # Storing avg_fitness values to a list
    if count_or_something == 500:  # This helps to obtain the plot of avg_fitness vs number of generations at the end
        plt.plot(range(count_or_something), avg_fitness_array)
        plt.show()
    return (new_population, avg_fitness)
