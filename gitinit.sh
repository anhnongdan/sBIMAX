#!/bin/bash
git submodule update --init --recursive
git submodule update --recursive --remote
cd $PWD/BimaxCore && git submodule update --init --recursive
