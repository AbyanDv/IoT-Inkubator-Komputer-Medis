#pragma once
#include "types.h"

namespace relays {
void begin();
void apply(const model::Reading &r);

// Fan on, both heaters off. Called the moment a fault trips.
void forceSafe();
}  // namespace relays
